#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
COMPOSE="docker compose -f $ROOT/docker-compose.smoke.yml"
BASE_URL=http://localhost:8087
ARCHIVE="$ROOT/artifacts/partneropen-connector-0.1.0.zip"

fail() {
    echo "WordPress smoke test failed: $*" >&2
    exit 1
}

cleanup() {
    $COMPOSE down -v --remove-orphans >/dev/null 2>&1 || true
}

trap cleanup EXIT INT TERM

run_wp() {
    $COMPOSE run --rm wpcli --allow-root --path=/var/www/html "$@"
}

run_wp_archive() {
    $COMPOSE run --rm -v "$ARCHIVE:/tmp/partneropen-connector.zip:ro" wpcli --allow-root --path=/var/www/html "$@"
}

wp_eval() {
    run_wp eval "$1"
}

http_headers() {
    curl -sS -D - -o /dev/null "$1"
}

assert_http_status() {
    expected=$1
    url=$2
    headers=$(http_headers "$url") || fail "HTTP request failed: $url"
    case "$headers" in
        *" $expected "*) ;;
        *) fail "Expected HTTP $expected from $url" ;;
    esac
    printf '%s' "$headers"
}

# Start from a clean database/volume so consent and pairing state are never inherited.
echo '[1/15] Reset disposable WordPress state and build the reinstall package.'
cleanup
sh "$ROOT/distribution/build-connector-plugin.sh" >/dev/null
$COMPOSE up -d db wp >/dev/null

until run_wp core version >/dev/null 2>&1; do
    sleep 2
done

if ! run_wp core is-installed >/dev/null 2>&1; then
    run_wp core install \
        --url="$BASE_URL" \
        --title='PartnerOpen Smoke' \
        --admin_user=admin \
        --admin_password=admin-password \
        --admin_email=smoke@example.test \
        --skip-email >/dev/null
fi

# (a) Activate only PartnerOpen Connector.
echo '[2/15] Activate the Connector and flush pretty-permalink routes.'
run_wp plugin activate partneropen-connector >/dev/null
run_wp rewrite structure '/%postname%/' --hard >/dev/null
run_wp rewrite flush --hard >/dev/null
if ! grep -q 'no Cloud copy exists in this milestone' "$ROOT/plugins/partneropen-connector/includes/Admin/StatusPage.php"; then
    fail "Global Pause owner copy still claims Cloud data are preserved"
fi

# (b) Local-first: a marked outbound request is blocked before consent.
echo '[3/15] Assert local-first outbound-request gating.'
blocked=$(wp_eval '
$reply = wp_remote_get("https://partneropen.com/health", array("partneropen_scope" => "cloud_connection"));
if (!is_wp_error($reply) || $reply->get_error_code() !== "partneropen_consent_required") {
    throw new RuntimeException("pre-consent outbound request was not blocked");
}
echo "partneropen_consent_required";
')
case "$blocked" in
    *partneropen_consent_required*) ;;
    *) fail "local-first consent gate did not return partneropen_consent_required" ;;
esac

# (c) Setup and grant every scope used by this smoke path.
echo '[4/15] Set up consent, issue the renamed pairing transient, and check the Cloud allowlist.'
run_wp option update partneropen_connection \
    '{"status":"local","site_id":"site-smoke","prefix":"partner","partner_email":"partner@example.test","cloud_base":"https://partneropen.com","policy_version":"2026-08-24","paired_at":0,"disconnected_at":0,"last_sync_at":0}' \
    --format=json >/dev/null
wp_eval '
\PartnerOpen\Connector\Domain\Consent::grant(array("cloud_connection", "content_sync", "agent_pack", "aggregate_metrics", "affiliate_service"), "2026-08-24");
$code = \PartnerOpen\Connector\Application\Pairing::issue_code();
if (strlen($code) !== 12) {
    throw new RuntimeException("pairing code was not 12 characters");
}
if (get_transient("partneropen_connector_pair_code") !== $code) {
    throw new RuntimeException("renamed pairing transient was not written");
}
echo $code;
' >/dev/null
pair_code=$(wp_eval 'echo get_transient("partneropen_connector_pair_code");')
[ -n "$pair_code" ] || fail "pairing code was not issued"

invalid_pair=$(wp_eval "
\$code = \"$pair_code\";
\$request = new WP_REST_Request(\"POST\", \"/partneropen/v1/pair\");
\$request->set_header(\"Content-Type\", \"application/json\");
\$request->set_body(wp_json_encode(array(\"code\" => \$code, \"cloud_base\" => \"https://not-allowlisted.example\")));
\$response = rest_do_request(\$request);
\$status = \$response instanceof WP_Error ? (int) ((\$response->get_error_data()[\"status\"] ?? 0)) : (int) \$response->get_status();
\$code_value = \$response instanceof WP_Error ? (string) \$response->get_error_code() : (string) ((\$response->get_data()[\"code\"] ?? \"\"));
if (\$status !== 400 || \$code_value !== \"partneropen_invalid_cloud_base\") {
    throw new RuntimeException(\"invalid cloud_base did not return the documented 400\");
}
if (get_transient(\"partneropen_connector_pair_code\") !== \$code) {
    throw new RuntimeException(\"invalid cloud_base consumed the pairing code\");
}
echo \"allowlist-rejected-code-preserved\";
")
case "$invalid_pair" in
    *allowlist-rejected-code-preserved*) ;;
    *) fail "Cloud allowlist assertion failed: $invalid_pair" ;;
esac

# (d) Pair through REST using the same code after the rejected host.
echo '[5/15] Pair with an allowlisted Cloud host.'
pair_json=$(wp_eval "
\$request = new WP_REST_Request(\"POST\", \"/partneropen/v1/pair\");
\$request->set_header(\"Content-Type\", \"application/json\");
\$request->set_body(wp_json_encode(array(\"code\" => \"$pair_code\", \"cloud_base\" => \"https://partneropen.com\")));
\$response = rest_do_request(\$request);
if (\$response instanceof WP_Error || \$response->get_status() !== 200) {
    throw new RuntimeException(\"pair failed: \" . wp_json_encode(\$response instanceof WP_Error ? \$response->get_error_data() : \$response->get_data()));
}
echo wp_json_encode(\$response->get_data());
")
pair_b64=$(printf '%s' "$pair_json" | base64 | tr -d '\n')
secret=$(wp_eval "
\$pair = json_decode(base64_decode(\"$pair_b64\"), true);
if (!is_array(\$pair) || empty(\$pair[\"secret\"])) {
    throw new RuntimeException(\"pair response did not contain a secret\");
}
echo \$pair[\"secret\"];
")
[ -n "$secret" ] || fail "pair secret was empty"
secret_b64=$(printf '%s' "$secret" | base64 | tr -d '\n')

# (e) Missing signed identity and scope headers are distinct authorization failures.
echo '[6/15] Reject signed requests missing X-PartnerOpen-Site or X-PartnerOpen-Scopes.'
auth_check=$(wp_eval "
\$path = \"/partneropen/v1/status\";
\$body = \"\";
\$timestamp = time();
\$secret = base64_decode(\"$secret_b64\");
\$make = static function (array \$headers) use (\$path, \$body): mixed {
    \$request = new WP_REST_Request(\"GET\", \$path);
    foreach (\$headers as \$key => \$value) {
        \$request->set_header(\$key, \$value);
    }
    return rest_do_request(\$request);
};
\$base = array(
    \"X-PartnerOpen-Site\" => \"site-smoke\",
    \"X-PartnerOpen-Timestamp\" => (string) \$timestamp,
    \"X-PartnerOpen-Nonce\" => \"smoke-auth-\" . wp_generate_uuid4(),
    \"X-PartnerOpen-Scopes\" => \"cloud_connection\",
);
\$base[\"X-PartnerOpen-Signature\"] = \PartnerOpen\\Connector\\Http\\Signature::sign(\"GET\", \$path, \$timestamp, \$base[\"X-PartnerOpen-Nonce\"], \$body, \$secret);
\$missing_site = \$base;
unset(\$missing_site[\"X-PartnerOpen-Site\"]);
\$missing_scope = \$base;
unset(\$missing_scope[\"X-PartnerOpen-Scopes\"]);
\$status_of = static function (mixed \$response): int {
    return \$response instanceof WP_Error ? (int) ((\$response->get_error_data()[\"status\"] ?? 0)) : (int) \$response->get_status();
};
\$code_of = static function (mixed \$response): string {
    if (\$response instanceof WP_Error) {
        return (string) \$response->get_error_code();
    }
    \$data = \$response->get_data();
    return is_array(\$data) ? (string) (\$data[\"code\"] ?? \"\") : \"\";
};
\$site_response = \$make(\$missing_site);
if (\$status_of(\$site_response) !== 401 || \$code_of(\$site_response) !== \"partneropen_signature_invalid\") {
    throw new RuntimeException(\"missing site header was accepted\");
}
\$scope_response = \$make(\$missing_scope);
if (\$status_of(\$scope_response) !== 403 || \$code_of(\$scope_response) !== \"partneropen_consent_required\") {
    throw new RuntimeException(\"missing scopes header was accepted\");
}
echo \"auth-headers-rejected\";
")
case "$auth_check" in
    *auth-headers-rejected*) ;;
    *) fail "signed-header authorization assertion failed: $auth_check" ;;
esac

# (f) Push a signed format-3 snapshot; the Connector assigns snapshot_version.
echo '[7/15] Publish a strict format-3 snapshot and assert exact GET /spaces fields.'
snapshot='{"version":3,"space":{"id":"space-reviews","slug":"reviews","title":"Partner reviews","status":"published"},"allowed_hosts":["dest.example"],"blocks":[{"type":"hero","heading":"Partner reviews","lede":"A factual public summary.","link_id":"l1","placement_id":"hero","label":"Open destination"},{"type":"text","html":"<p>Read the disclosed destination.</p>"}],"links":{"l1":{"destination":"https://dest.example/path","label":"Open destination","disclosure":"Disclosure: This is an affiliate link.","status":"active","placements":["hero"]}},"agent":{"summary":"Public review summary","instructions":["Use the public page."],"entities":[{"name":"Partner reviews","type":"Space"}]}}'
snapshot_b64=$(printf '%s' "$snapshot" | base64 | tr -d '\n')
publish_result=$(wp_eval "
\$body = base64_decode(\"$snapshot_b64\");
\$path = \"/partneropen/v1/spaces/reviews/snapshot\";
\$timestamp = time();
\$nonce = \"smoke-publish-\" . wp_generate_uuid4();
\$signature = \PartnerOpen\\Connector\\Http\\Signature::sign(\"PUT\", \$path, \$timestamp, \$nonce, \$body, \"$secret\");
\$request = new WP_REST_Request(\"PUT\", \$path);
\$request->set_body(\$body);
\$request->set_header(\"Content-Type\", \"application/json\");
\$request->set_header(\"X-PartnerOpen-Site\", \"site-smoke\");
\$request->set_header(\"X-PartnerOpen-Timestamp\", (string) \$timestamp);
\$request->set_header(\"X-PartnerOpen-Nonce\", \$nonce);
\$request->set_header(\"X-PartnerOpen-Signature\", \$signature);
\$request->set_header(\"X-PartnerOpen-Scopes\", \"cloud_connection,content_sync,agent_pack,aggregate_metrics,affiliate_service\");
\$response = rest_do_request(\$request);
if (\$response instanceof WP_Error || ! in_array(\$response->get_status(), array(200, 201), true)) {
    throw new RuntimeException(\"snapshot publish failed: \" . wp_json_encode(\$response instanceof WP_Error ? \$response->get_error_data() : \$response->get_data()));
}
\$spaces_path = \"/partneropen/v1/spaces\";
\$spaces_timestamp = time();
\$spaces_nonce = \"smoke-spaces-\" . wp_generate_uuid4();
\$spaces_request = new WP_REST_Request(\"GET\", \$spaces_path);
\$spaces_request->set_header(\"X-PartnerOpen-Site\", \"site-smoke\");
\$spaces_request->set_header(\"X-PartnerOpen-Timestamp\", (string) \$spaces_timestamp);
\$spaces_request->set_header(\"X-PartnerOpen-Nonce\", \$spaces_nonce);
\$spaces_request->set_header(\"X-PartnerOpen-Signature\", \PartnerOpen\\Connector\\Http\\Signature::sign(\"GET\", \$spaces_path, \$spaces_timestamp, \$spaces_nonce, \"\", \"$secret\"));
\$spaces_request->set_header(\"X-PartnerOpen-Scopes\", \"cloud_connection\");
\$spaces_response = rest_do_request(\$spaces_request);
if (\$spaces_response instanceof WP_Error || \$spaces_response->get_status() !== 200) {
    throw new RuntimeException(\"GET /spaces failed\");
}
\$spaces_data = \$spaces_response->get_data();
\$spaces = is_array(\$spaces_data) ? \$spaces_data : array();
if (array_keys(\$spaces) !== array(\"spaces\")) {
    throw new RuntimeException(\"GET /spaces added an undocumented top-level field\");
}
\$expected = array(\"id\", \"slug\", \"title\", \"status\", \"snapshot_version\", \"published_at\");
foreach (\$spaces[\"spaces\"] as \$space) {
    \$keys = array_keys(\$space);
    sort(\$keys);
    \$sorted_expected = \$expected;
    sort(\$sorted_expected);
    if (\$keys !== \$sorted_expected) {
        throw new RuntimeException(\"GET /spaces exposed undocumented fields\");
    }
}
echo wp_json_encode(array(\"status\" => \$response->get_status(), \"data\" => \$response->get_data()));
")
case "$publish_result" in
    *'"status":201'*|*'"status":200'*) ;;
    *) fail "snapshot publish did not return HTTP 201 (create) or 200 (replacement): $publish_result" ;;
esac
run_wp eval '\PartnerOpen\Connector\Public\Router::flush();' >/dev/null

# (g) Invalid snapshots, including remote images, return 400 with field paths.
echo '[8/15] Reject malformed snapshots and remote image URLs with 400 errors.'
invalid_snapshot='{"version":3,"space":{"id":"space-reviews","slug":"reviews","title":"Partner reviews","status":"published"},"blocks":[{"type":"not-a-real-block"}],"links":{}}'
invalid_snapshot_b64=$(printf '%s' "$invalid_snapshot" | base64 | tr -d '\n')
remote_image_snapshot='{"version":3,"space":{"id":"space-reviews","slug":"reviews","title":"Partner reviews","status":"published"},"blocks":[{"type":"image","url":"https://images.example.test/photo.jpg","alt":"Remote"}],"links":{}}'
remote_image_b64=$(printf '%s' "$remote_image_snapshot" | base64 | tr -d '\n')
invalid_check=$(wp_eval "
\$secret = \"$secret\";
\$send = static function (string \$body, string \$nonce) use (\$secret): mixed {
    \$path = \"/partneropen/v1/spaces/reviews/snapshot\";
    \$timestamp = time();
    \$request = new WP_REST_Request(\"PUT\", \$path);
    \$request->set_body(\$body);
    \$request->set_header(\"Content-Type\", \"application/json\");
    \$request->set_header(\"X-PartnerOpen-Site\", \"site-smoke\");
    \$request->set_header(\"X-PartnerOpen-Timestamp\", (string) \$timestamp);
    \$request->set_header(\"X-PartnerOpen-Nonce\", \$nonce);
    \$request->set_header(\"X-PartnerOpen-Signature\", \PartnerOpen\\Connector\\Http\\Signature::sign(\"PUT\", \$path, \$timestamp, \$nonce, \$body, \$secret));
    \$request->set_header(\"X-PartnerOpen-Scopes\", \"cloud_connection,content_sync,agent_pack,aggregate_metrics,affiliate_service\");
    return rest_do_request(\$request);
};
\$status_of = static function (mixed \$response): int {
    return \$response instanceof WP_Error ? (int) ((\$response->get_error_data()[\"status\"] ?? 0)) : (int) \$response->get_status();
};
\$data_of = static function (mixed \$response): array {
    if (\$response instanceof WP_Error) {
        \$data = \$response->get_error_data();
        return is_array(\$data) ? \$data : array();
    }
    \$data = \$response->get_data();
    return is_array(\$data) ? \$data : array();
};
\$errors_of = static function (array \$data): mixed {
    if (is_array(\$data[\"errors\"] ?? null)) {
        return \$data[\"errors\"];
    }
    if (is_array(\$data[\"data\"] ?? null) && is_array(\$data[\"data\"][\"errors\"] ?? null)) {
        return \$data[\"data\"][\"errors\"];
    }
    return null;
};
\$invalid = \$send(base64_decode(\"$invalid_snapshot_b64\"), \"smoke-invalid-\" . wp_generate_uuid4());
\$invalid_data = \$data_of(\$invalid);
\$invalid_code = \$invalid instanceof WP_Error ? \$invalid->get_error_code() : (string) (\$invalid->get_data()[\"code\"] ?? \"\");
if (\$status_of(\$invalid) !== 400 || \$invalid_code !== \"partneropen_invalid_snapshot\" || ! is_array(\$errors_of(\$invalid_data)) || \$errors_of(\$invalid_data) === array()) {
    throw new RuntimeException(\"invalid snapshot did not return 400 with data.errors\");
}
\$remote = \$send(base64_decode(\"$remote_image_b64\"), \"smoke-remote-image-\" . wp_generate_uuid4());
\$remote_data = \$data_of(\$remote);
\$remote_code = \$remote instanceof WP_Error ? \$remote->get_error_code() : (string) (\$remote->get_data()[\"code\"] ?? \"\");
if (\$status_of(\$remote) !== 400 || \$remote_code !== \"partneropen_invalid_snapshot\" || ! is_array(\$errors_of(\$remote_data))) {
    throw new RuntimeException(\"remote image URL was not rejected with field errors\");
}
echo \"invalid-snapshots-rejected\";
")
case "$invalid_check" in
    *invalid-snapshots-rejected*) ;;
    *) fail "strict snapshot assertion failed: $invalid_check" ;;
esac

# (h) Affiliate consent controls anchors and resolver traffic.
echo '[9/15] Enforce affiliate_service on rendering and resolution.'
affiliate_check=$(wp_eval '
$snapshot = \PartnerOpen\Connector\Application\SnapshotStore::get("space-reviews");
if (!is_array($snapshot)) {
    throw new RuntimeException("published snapshot was not stored");
}
$html = \PartnerOpen\Connector\Public\SnapshotRenderer::render($snapshot, home_url("/partneropen/go"));
if (strpos($html, "/partneropen/go/l1/hero") === false || strpos($html, "Goes to dest.example") === false || strpos($html, "rel=\"sponsored nofollow noopener\"") === false) {
    throw new RuntimeException("granted affiliate link did not render resolver anchor/disclosure");
}
if (strpos($html, "https://dest.example/path") !== false) {
    throw new RuntimeException("rendered HTML exposed raw external destination");
}
$result = \PartnerOpen\Connector\Public\LinkResolver::resolve("l1", "hero");
if (($result["status"] ?? 0) !== 302) {
    throw new RuntimeException("granted affiliate resolver did not return 302");
}
\PartnerOpen\Connector\Domain\Consent::revoke("affiliate_service");
$withdrawn_html = \PartnerOpen\Connector\Public\SnapshotRenderer::render($snapshot, home_url("/partneropen/go"));
if (strpos($withdrawn_html, "Open destination") === false || strpos($withdrawn_html, "/partneropen/go") !== false || strpos($withdrawn_html, "<a ") !== false) {
    throw new RuntimeException("withdrawn affiliate link was not plain text without resolver href");
}
$withdrawn_result = \PartnerOpen\Connector\Public\LinkResolver::resolve("l1", "hero");
if (($withdrawn_result["status"] ?? 0) !== 404 || ($withdrawn_result["reason"] ?? "") !== "consent") {
    throw new RuntimeException("withdrawn affiliate resolver did not return consent 404");
}
\PartnerOpen\Connector\Domain\Consent::grant(array("affiliate_service"), "2026-08-24");
echo "affiliate-gate-ok";
')
case "$affiliate_check" in
    *affiliate-gate-ok*) ;;
    *) fail "affiliate_service assertion failed: $affiliate_check" ;;
esac

# (i) Aggregate consent controls collection, not redirect availability.
echo '[10/15] Enforce aggregate_metrics at collection.'
metrics_check=$(wp_eval '
$before = \PartnerOpen\Connector\Application\ClickCounter::all();
\PartnerOpen\Connector\Domain\Consent::revoke("aggregate_metrics");
$result = \PartnerOpen\Connector\Public\LinkResolver::resolve("l1", "hero");
if (($result["status"] ?? 0) !== 302) {
    throw new RuntimeException("aggregate withdrawal incorrectly blocked an otherwise valid resolver");
}
$after = \PartnerOpen\Connector\Application\ClickCounter::all();
if ($after !== $before) {
    throw new RuntimeException("aggregate withdrawal still wrote a counter");
}
\PartnerOpen\Connector\Domain\Consent::grant(array("aggregate_metrics"), "2026-08-24");
echo "aggregate-gate-ok";
')
case "$metrics_check" in
    *aggregate-gate-ok*) ;;
    *) fail "aggregate_metrics assertion failed: $metrics_check" ;;
esac

# (j) Suspend -> sync -> resume and Space-scoped link suspension.
echo '[11/15] Suspend, sync, verify link 404, then resume the published Space.'
suspend_check=$(wp_eval "
\$secret = \"$secret\";
\$send = static function (string \$method, string \$path, string \$body, string \$nonce) use (\$secret): mixed {
    \$timestamp = time();
    \$request = new WP_REST_Request(\$method, \$path);
    if (\$body !== \"\") {
        \$request->set_body(\$body);
        \$request->set_header(\"Content-Type\", \"application/json\");
    }
    \$request->set_header(\"X-PartnerOpen-Site\", \"site-smoke\");
    \$request->set_header(\"X-PartnerOpen-Timestamp\", (string) \$timestamp);
    \$request->set_header(\"X-PartnerOpen-Nonce\", \$nonce);
    \$request->set_header(\"X-PartnerOpen-Signature\", \PartnerOpen\\Connector\\Http\\Signature::sign(\$method, \$path, \$timestamp, \$nonce, \$body, \$secret));
    \$request->set_header(\"X-PartnerOpen-Scopes\", \"cloud_connection,content_sync,agent_pack,aggregate_metrics,affiliate_service\");
    return rest_do_request(\$request);
};
\$suspend = \$send(\"POST\", \"/partneropen/v1/spaces/reviews/suspend\", \"\", \"smoke-suspend-\" . wp_generate_uuid4());
if (\$suspend instanceof WP_Error || \$suspend->get_status() !== 200 || (\$suspend->get_data()[\"space\"][\"status\"] ?? \"\") !== \"suspended\") {
    throw new RuntimeException(\"Space suspend failed\");
}
\$sync = \$send(\"PUT\", \"/partneropen/v1/spaces/reviews/snapshot\", base64_decode(\"$snapshot_b64\"), \"smoke-sync-suspended-\" . wp_generate_uuid4());
if (\$sync instanceof WP_Error || \$sync->get_status() !== 200 || (\$sync->get_data()[\"space\"][\"status\"] ?? \"\") !== \"suspended\") {
    throw new RuntimeException(\"sync did not preserve suspended Space status\");
}
\$blocked = \PartnerOpen\\Connector\\Public\\LinkResolver::resolve(\"l1\", \"hero\");
if ((\$blocked[\"status\"] ?? 0) !== 404 || (\$blocked[\"reason\"] ?? \"\") !== \"suspended\") {
    throw new RuntimeException(\"suspended Space link id still resolved\");
}
\$resume = \$send(\"POST\", \"/partneropen/v1/spaces/reviews/resume\", \"\", \"smoke-resume-\" . wp_generate_uuid4());
if (\$resume instanceof WP_Error || \$resume->get_status() !== 200 || (\$resume->get_data()[\"space\"][\"status\"] ?? \"\") !== \"published\") {
    throw new RuntimeException(\"Space resume failed\");
}
\$restored = \PartnerOpen\\Connector\\Public\\LinkResolver::resolve(\"l1\", \"hero\");
if ((\$restored[\"status\"] ?? 0) !== 302) {
    throw new RuntimeException(\"resumed Space link did not resolve\");
}
echo \"suspend-sync-resume-ok\";
")
case "$suspend_check" in
    *suspend-sync-resume-ok*) ;;
    *) fail "Space suspension lifecycle assertion failed: $suspend_check" ;;
esac

# (k) Agent-pack withdrawal and plain-text text blocks remain gated; public stylesheet is enqueued.
echo '[12/15] Check agent-pack withdrawal and public stylesheet enqueue.'
wp_eval '\PartnerOpen\Connector\Domain\Consent::revoke("agent_pack");' >/dev/null
for agent_url in "$BASE_URL/partner/AGENTS.md" "$BASE_URL/partner/agents.md" "$BASE_URL/partner/llms.txt" "$BASE_URL/partner/ai-context.json" "$BASE_URL/partner/manifest.json" "$BASE_URL/partner/sitemap.xml"; do
    agent_headers=$(assert_http_status 404 "$agent_url")
    case "$agent_headers" in
        *"Cache-Control: no-store, no-cache, must-revalidate"*) ;;
        *) fail "agent_pack withdrawal lacked no-store semantics for $agent_url" ;;
    esac
done
wp_eval '\PartnerOpen\Connector\Domain\Consent::grant(array("agent_pack"), "2026-08-24");' >/dev/null
stylesheet_check=$(wp_eval '
// Router::dispatch() exits after rendering, so invoke the same private enqueue
// step used immediately before the Space page response and inspect the WordPress queue.
$reflection = new ReflectionMethod(\PartnerOpen\Connector\Public\Router::class, "enqueue_stylesheet");
$reflection->setAccessible(true);
$reflection->invoke(new \PartnerOpen\Connector\Public\Router());
if (!function_exists("wp_style_is") || !wp_style_is("partneropen-connector-public", "enqueued")) {
    throw new RuntimeException("public stylesheet was not enqueued");
}
echo "stylesheet-enqueued";
')
case "$stylesheet_check" in
    *stylesheet-enqueued*) ;;
    *) fail "public stylesheet assertion failed: $stylesheet_check" ;;
esac
space_html=$(curl -sS "$BASE_URL/partner/reviews/")
case "$space_html" in
    *partneropen.css*) ;;
    *) fail "public Space response did not print the enqueued stylesheet link" ;;
esac

# (l) Global Pause makes resolver, agent asset, and Space page 404 with no-store semantics, then Resume restores them.
echo '[13/15] Check Global Pause and resume overlay.'
paused_check=$(wp_eval '
\PartnerOpen\Connector\Infrastructure\Options::set_paused(true);
$result = \PartnerOpen\Connector\Public\LinkResolver::resolve("l1", "hero");
if (($result["status"] ?? 0) !== 404 || ($result["reason"] ?? "") !== "paused") {
    throw new RuntimeException("paused resolver did not return 404 paused");
}
echo "paused";
')
case "$paused_check" in
    *paused*) ;;
    *) fail "Global Pause did not pause resolver" ;;
esac
for public_url in "$BASE_URL/partner/reviews/" "$BASE_URL/partner/AGENTS.md" "$BASE_URL/partneropen/go/l1/hero"; do
    paused_headers=$(assert_http_status 404 "$public_url")
    case "$paused_headers" in
        *"Cache-Control: no-store, no-cache, must-revalidate"*) ;;
        *) fail "Global Pause response lacked no-store semantics for $public_url" ;;
    esac
    case "$paused_headers" in
        *"X-Robots-Tag: noindex"*) ;;
        *) fail "Global Pause response lacked noindex semantics for $public_url" ;;
    esac
done
wp_eval '\PartnerOpen\Connector\Infrastructure\Options::set_paused(false);' >/dev/null
assert_http_status 200 "$BASE_URL/partner/reviews/" >/dev/null
resume_headers=$(assert_http_status 302 "$BASE_URL/partneropen/go/l1/hero")
case "$resume_headers" in
    *"Location: https://dest.example/path"*) ;;
    *) fail "Resume did not restore resolver redirect" ;;
esac

# (m) Signed disconnect revokes the secret, keeps local snapshots, and rejects later signed requests.
echo '[14/15] Disconnect and verify key revocation while local snapshots remain.'
disconnect_check=$(wp_eval "
\$path = \"/partneropen/v1/disconnect\";
\$timestamp = time();
\$nonce = \"smoke-disconnect-\" . wp_generate_uuid4();
\$body = \"\";
\$secret = \"$secret\";
\$signature = \PartnerOpen\\Connector\\Http\\Signature::sign(\"POST\", \$path, \$timestamp, \$nonce, \$body, \$secret);
\$request = new WP_REST_Request(\"POST\", \$path);
\$request->set_header(\"X-PartnerOpen-Site\", \"site-smoke\");
\$request->set_header(\"X-PartnerOpen-Timestamp\", (string) \$timestamp);
\$request->set_header(\"X-PartnerOpen-Nonce\", \$nonce);
\$request->set_header(\"X-PartnerOpen-Signature\", \$signature);
\$request->set_header(\"X-PartnerOpen-Scopes\", \"cloud_connection\");
\$response = rest_do_request(\$request);
if (\$response instanceof WP_Error || \$response->get_status() !== 200 || (\$response->get_data()[\"status\"] ?? \"\") !== \"disconnected\") {
    throw new RuntimeException(\"disconnect did not return status disconnected\");
}
if (\PartnerOpen\\Connector\\Infrastructure\\Options::secret() !== \"\") {
    throw new RuntimeException(\"disconnect did not revoke the secret\");
}
if (\PartnerOpen\\Connector\\Application\\SnapshotStore::get(\"space-reviews\") === null) {
    throw new RuntimeException(\"disconnect deleted the local snapshot\");
}
echo \"disconnect-ok\";
")
case "$disconnect_check" in
    *disconnect-ok*) ;;
    *) fail "disconnect assertion failed: $disconnect_check" ;;
esac

# (n) Uninstall cleanup, then reactivate for a working final state. The plugin directory is
# bind-mounted from the repository, so `wp plugin uninstall` cannot delete it; run the real
# uninstall routine the way WordPress does instead, then re-activate the same code.
echo '[15/15] Run the uninstall routine, assert option cleanup, then reactivate.'
run_wp plugin deactivate partneropen-connector >/dev/null
run_wp eval 'define("WP_UNINSTALL_PLUGIN", "partneropen-connector/partneropen-connector.php"); require WP_PLUGIN_DIR . "/partneropen-connector/uninstall.php";' >/dev/null
remaining=$(run_wp option list --search='partneropen_' --field=option_name 2>/dev/null || true)
case "$remaining" in
    "") ;;
    *) fail "uninstall left partneropen_* options: $remaining" ;;
esac
run_wp plugin activate partneropen-connector >/dev/null
run_wp rewrite flush --hard >/dev/null
run_wp plugin is-active partneropen-connector >/dev/null
run_wp option get partneropen_connection --format=json >/dev/null
scheduled=$(run_wp cron event list --fields=hook --format=csv 2>/dev/null || true)
case "$scheduled" in
    *partneropen_connector_prune_clicks*) ;;
    *) fail "reactivated plugin did not reschedule click pruning" ;;
esac

printf '%s\n' 'WordPress smoke test: OK'
