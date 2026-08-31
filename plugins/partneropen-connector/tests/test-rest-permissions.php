<?php

declare(strict_types=1);

$options = [];
function get_option(string $key, mixed $default = false): mixed
{
    global $options;
    return array_key_exists($key, $options) ? $options[$key] : $default;
}
function update_option(string $key, mixed $value, mixed $autoload = true): bool
{
    global $options;
    $options[$key] = $value;
    return true;
}
class WP_Error
{
    public function __construct(
        public string $code,
        public string $message,
        public array $data = [],
    ) {
    }
}
function home_url(string $path = '/'): string
{
    return 'https://site.test' . $path;
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Infrastructure/Options.php';
require_once __DIR__ . '/../includes/Domain/Block.php';
require_once __DIR__ . '/../includes/Domain/Link.php';
require_once __DIR__ . '/../includes/Domain/Consent.php';
require_once __DIR__ . '/../includes/Http/Signature.php';
require_once __DIR__ . '/../includes/Application/ClickCounter.php';
require_once __DIR__ . '/../includes/Application/Pairing.php';
require_once __DIR__ . '/../includes/Application/SpaceRegistry.php';
require_once __DIR__ . '/../includes/Application/SnapshotStore.php';
require_once __DIR__ . '/../includes/Support/Validation.php';
require_once __DIR__ . '/../includes/Http/RestApi.php';

use PartnerOpen\Connector\Application\SpaceRegistry;
use PartnerOpen\Connector\Application\SnapshotStore;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Http\RestApi;
use PartnerOpen\Connector\Http\Signature;
use PartnerOpen\Connector\Infrastructure\Options;

$options = Options::defaults();
Options::save_connection([
    'status' => 'connected',
    'site_id' => 'site-1',
]);
Options::set_secret('secret');
Consent::grant(['cloud_connection'], '1');
$body = '';
$timestamp = time();
$nonce = 'auth-1';
$headers = [
    'X-PartnerOpen-Site' => 'site-1',
    'X-PartnerOpen-Timestamp' => (string) $timestamp,
    'X-PartnerOpen-Nonce' => $nonce,
    'X-PartnerOpen-Signature' => Signature::sign('GET', '/partneropen/v1/status', $timestamp, $nonce, $body, 'secret'),
    'X-PartnerOpen-Scopes' => 'cloud_connection',
];
if (! RestApi::authorize('GET', '/partneropen/v1/status', $headers, $body, 'cloud_connection')) {
    throw new RuntimeException('valid signed authorized request should pass');
}
if (RestApi::authorization_error('GET', '/partneropen/v1/status', [...$headers, 'X-PartnerOpen-Site' => ''], $body, 'cloud_connection') !== 'partneropen_signature_invalid') {
    throw new RuntimeException('missing site header should be a signature error');
}
$mismatched_site = $headers;
$mismatched_site['X-PartnerOpen-Site'] = 'site-2';
if (RestApi::authorization_error('GET', '/partneropen/v1/status', $mismatched_site, $body, 'cloud_connection') !== 'partneropen_signature_invalid') {
    throw new RuntimeException('mismatched site header should be a signature error');
}
$missing_scopes = $headers;
unset($missing_scopes['X-PartnerOpen-Scopes']);
if (RestApi::authorization_error('GET', '/partneropen/v1/status', $missing_scopes, $body, 'cloud_connection') !== 'partneropen_consent_required') {
    throw new RuntimeException('missing scopes header should be a consent error');
}
$lacking_scope = $headers;
$lacking_scope['X-PartnerOpen-Scopes'] = 'content_sync';
if (RestApi::authorization_error('GET', '/partneropen/v1/status', $lacking_scope, $body, 'cloud_connection') !== 'partneropen_consent_required') {
    throw new RuntimeException('lacking scope should be a consent error');
}
if (RestApi::authorize('GET', '/partneropen/v1/status', $headers, 'tampered', 'cloud_connection')) {
    throw new RuntimeException('tampered body should fail authorization');
}
Consent::revoke('cloud_connection');
if (RestApi::authorize('GET', '/partneropen/v1/status', $headers, $body, 'cloud_connection')) {
    throw new RuntimeException('missing consent should fail authorization');
}
Consent::grant(['cloud_connection'], '1');
Options::save_connection(['status' => 'disconnected']);
$nonce = 'auth-2';
$headers['X-PartnerOpen-Nonce'] = $nonce;
$headers['X-PartnerOpen-Signature'] = Signature::sign('GET', '/partneropen/v1/status', $timestamp, $nonce, $body, 'secret');
if (RestApi::authorize('GET', '/partneropen/v1/status', $headers, $body, 'cloud_connection')) {
    throw new RuntimeException('disconnected connector should fail authorization');
}

$pairing_code = \PartnerOpen\Connector\Application\Pairing::issue_code();
$invalid_pair = RestApi::pair([
    'body' => [
        'code' => $pairing_code,
        'cloud_base' => 'https://not-partneropen.example',
    ],
]);
if (! $invalid_pair instanceof WP_Error
    || $invalid_pair->code !== 'partneropen_invalid_cloud_base'
    || ($invalid_pair->data['status'] ?? 0) !== 400
) {
    throw new RuntimeException('a non-allowlisted cloud host should be rejected');
}
$valid_pair = RestApi::pair([
    'body' => [
        'code' => $pairing_code,
        'cloud_base' => 'https://partneropen.com',
    ],
]);
if (! is_array($valid_pair) || ($valid_pair['site_id'] ?? '') !== 'site-1') {
    throw new RuntimeException('a pairing code should remain usable after cloud validation fails');
}

$snapshot_body = [
    'version' => 3,
    'space' => ['slug' => 'fresh-space', 'title' => 'Fresh Space'],
    'allowed_hosts' => [],
    'blocks' => [],
    'links' => [],
];

$invalid_snapshot = RestApi::snapshot([
    'space' => 'invalid-space',
    'body' => [
        'version' => 3,
        'space' => ['slug' => 'invalid-space'],
        'blocks' => 'not-an-array',
        'links' => [],
    ],
]);
if (! $invalid_snapshot instanceof WP_Error
    || $invalid_snapshot->code !== 'partneropen_invalid_snapshot'
    || ($invalid_snapshot->data['status'] ?? 0) !== 400
    || ! is_array($invalid_snapshot->data['errors'] ?? null)
    || $invalid_snapshot->data['errors'] === []
) {
    throw new RuntimeException('invalid snapshots should return a list of field paths');
}
$first = RestApi::snapshot(['space' => 'fresh-space', 'body' => $snapshot_body]);
if (! is_array($first) || ($first['snapshot']['version'] ?? 0) !== 3 || ($first['space']['snapshot_version'] ?? 0) !== 1) {
    throw new RuntimeException('format version 3 should publish revision one');
}
$unsupported_two = RestApi::snapshot(['space' => 'fresh-space', 'body' => [...$snapshot_body, 'version' => 2]]);
if (! $unsupported_two instanceof WP_Error || $unsupported_two->code !== 'partneropen_unsupported_snapshot_version' || ($unsupported_two->data['status'] ?? 0) !== 400) {
    throw new RuntimeException('format version 2 should be rejected with HTTP 400');
}
$unsupported_four = RestApi::snapshot(['space' => 'fresh-space', 'body' => [...$snapshot_body, 'version' => 4]]);
if (! $unsupported_four instanceof WP_Error || $unsupported_four->code !== 'partneropen_unsupported_snapshot_version' || ($unsupported_four->data['status'] ?? 0) !== 400) {
    throw new RuntimeException('format version 4 should be rejected with HTTP 400');
}
$absent = $snapshot_body;
unset($absent['version']);
$second = RestApi::snapshot(['space' => 'fresh-space', 'body' => $absent]);
if (! is_array($second)
    || ($second['snapshot']['version'] ?? 0) !== 3
    || ($second['space']['snapshot_version'] ?? 0) !== 2
) {
    throw new RuntimeException('a snapshot without a format version should normalize to 3 and increment revision two');
}

SpaceRegistry::suspend('space-fresh-space');
$suspended = RestApi::snapshot(['space' => 'fresh-space', 'body' => $snapshot_body]);
if (! is_array($suspended)
    || ($suspended['space']['status'] ?? '') !== 'suspended'
    || ($suspended['space']['snapshot_version'] ?? 0) !== 3
    || ($suspended['snapshot']['version'] ?? 0) !== 3
) {
    throw new RuntimeException('publishing a suspended Space should keep it suspended while syncing the snapshot');
}
SpaceRegistry::resume('space-fresh-space');
$resumed = SpaceRegistry::find('space-fresh-space');
if (($resumed['status'] ?? '') !== 'published' || SnapshotStore::get('space-fresh-space') === null) {
    throw new RuntimeException('resuming a Space should publish the synced snapshot');
}


$foreign = json_encode([
    'version' => 3,
    'space' => ['slug' => 'canonical-space', 'title' => 'Canonical Space'],
    'seo' => ['title' => 'T', 'description' => 'D', 'canonical' => 'https://elsewhere.example/partner/canonical-space/'],
    'blocks' => [['type' => 'text', 'html' => '<p>Body</p>']],
    'links' => [],
]);
$dropped = RestApi::snapshot(['space' => 'canonical-space', 'body' => $foreign]);
if (! $dropped instanceof WP_Error
    || $dropped->code !== 'partneropen_invalid_snapshot'
    || ($dropped->data['status'] ?? 0) !== 400
    || ! is_array($dropped->data['errors'] ?? null)
    || $dropped->data['errors'] === []
) {
    throw new RuntimeException('a foreign canonical should fail strict snapshot validation');
}
$own = json_encode([
    'version' => 3,
    'space' => ['slug' => 'canonical-space', 'title' => 'Canonical Space'],
    'seo' => ['title' => 'T', 'description' => 'D', 'canonical' => 'https://site.test/partner/canonical-space/'],
    'blocks' => [['type' => 'text', 'html' => '<p>Body</p>']],
    'links' => [],
]);
$kept = RestApi::snapshot(['space' => 'canonical-space', 'body' => $own]);
if (! is_array($kept) || ($kept['snapshot']['seo']['canonical'] ?? '') !== 'https://site.test/partner/canonical-space/') {
    throw new RuntimeException('a same-origin canonical must be preserved');
}

$space_response = RestApi::spaces();
$space_keys = ['id', 'slug', 'title', 'status', 'snapshot_version', 'published_at'];
foreach ($space_response['spaces'] as $space_summary) {
    if (array_keys($space_summary) !== $space_keys) {
        throw new RuntimeException('GET /spaces should expose exactly the documented fields');
    }
}
echo "PartnerOpen REST permission tests: OK\n";
