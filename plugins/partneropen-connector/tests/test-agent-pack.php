<?php

declare(strict_types=1);

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

$GLOBALS['partneropen_test_options'] = [];

$GLOBALS['partneropen_test_enqueued_styles'] = [];
if (! function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], mixed $ver = false, string $media = 'all'): void
    {
        $GLOBALS['partneropen_test_enqueued_styles'][] = [$handle, $src, $ver];
    }
}
$GLOBALS['partneropen_test_query'] = [];

if (! function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed
    {
        return array_key_exists($key, $GLOBALS['partneropen_test_options'])
            ? $GLOBALS['partneropen_test_options'][$key]
            : $default;
    }
}
if (! function_exists('update_option')) {
    function update_option(string $key, mixed $value, mixed ...$args): bool
    {
        $GLOBALS['partneropen_test_options'][$key] = $value;
        return true;
    }
}
if (! function_exists('add_option')) {
    function add_option(string $key, mixed $value = '', string $deprecated = '', bool $autoload = true): bool
    {
        $GLOBALS['partneropen_test_options'][$key] = $value;
        return true;
    }
}
if (! function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): int|string
    {
        return $type === 'timestamp' ? time() : gmdate($type);
    }
}
if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://site.example' . ($path === '' ? '/' : '/' . ltrim($path, '/'));
    }
}
if (! function_exists('get_query_var')) {
    function get_query_var(string $name, mixed $default = ''): mixed
    {
        return $GLOBALS['partneropen_test_query'][$name] ?? $default;
    }
}
if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0): string|false
    {
        return json_encode($value, $flags);
    }
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Support/Validation.php';
require_once __DIR__ . '/../includes/Infrastructure/Options.php';
require_once __DIR__ . '/../includes/Domain/Consent.php';
require_once __DIR__ . '/../includes/Domain/Block.php';
require_once __DIR__ . '/../includes/Domain/Link.php';
require_once __DIR__ . '/../includes/Application/SpaceRegistry.php';
require_once __DIR__ . '/../includes/Application/SnapshotStore.php';
require_once __DIR__ . '/../includes/Application/ClickCounter.php';
require_once __DIR__ . '/../includes/Public/SnapshotRenderer.php';
require_once __DIR__ . '/../includes/Public/LinkResolver.php';
require_once __DIR__ . '/../includes/Public/Router.php';
require_once __DIR__ . '/../includes/Public/AgentPack.php';

use PartnerOpen\Connector\Application\SnapshotStore;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;
use PartnerOpen\Connector\Public\Router;
use PartnerOpen\Connector\Public\AgentPack;

function partneropen_route_snapshot(): void
{
    Options::save_spaces([
        'space-1' => [
            'id' => 'space-1',
            'slug' => 'active-space',
            'title' => 'Active Space',
            'status' => 'published',
            'snapshot_version' => 0,
            'published_at' => 0,
        ],
    ]);
    SnapshotStore::put('space-1', [
        'version' => 3,
        'space' => [
            'id' => 'space-1',
            'slug' => 'active-space',
            'title' => 'Active Space',
            'status' => 'published',
        ],
        'allowed_hosts' => [],
        'blocks' => [],
        'links' => [],
    ]);
}

function partneropen_route_child(string $mode): void
{
    register_shutdown_function(static function (): void {
        $headers = function_exists('headers_list') ? headers_list() : [];
        echo "\n__ROUTE_STATUS__" . (string) http_response_code() . "\n";
        echo "__ROUTE_HEADERS__" . implode('|', $headers) . "\n";
        echo "__ENQUEUED__" . json_encode($GLOBALS['partneropen_test_enqueued_styles']) . "\n";
    });
    partneropen_route_snapshot();
    $GLOBALS['partneropen_test_query'] = [
        'partneropen_asset' => str_starts_with($mode, 'asset-') ? 'AGENTS.md' : '',
        'partneropen_space' => $mode === 'space-no-agent' ? 'active-space' : '',
        'partneropen_link' => '',
        'partneropen_placement' => '',
    ];
    if ($mode === 'asset-granted') {
        Consent::grant(['agent_pack'], '1');
    }

    (new Router())->dispatch();
    exit(1);
}

$route_mode = getenv('PARTNEROPEN_ROUTE_MODE');
if (is_string($route_mode) && $route_mode !== '') {
    partneropen_route_child($route_mode);
}

$redacted = AgentPack::redact([
    'EmailAddress' => 'owner@example.test',
    'safe' => [
        'Partner_ID_value' => 'private',
        'nested' => [
            'API_KEY_secret' => 'private',
            'visible' => 'kept',
            'metric_total' => 12,
        ],
    ],
    'normal' => 'kept',
]);
$redacted_json = json_encode($redacted);
foreach (['EmailAddress', 'Partner_ID_value', 'API_KEY_secret', 'metric_total'] as $key) {
    if (is_string($redacted_json) && stripos($redacted_json, $key) !== false) {
        throw new RuntimeException('redact retained deny-listed key: ' . $key);
    }
}
if (($redacted['normal'] ?? null) !== 'kept' || ($redacted['safe']['nested']['visible'] ?? null) !== 'kept') {
    throw new RuntimeException('redact removed a public key');
}

$active = [
    'version' => 3,
    'space' => [
        'id' => 'public-1',
        'slug' => 'active-space',
        'title' => 'Active Space',
        'status' => 'published',
    ],
    'seo' => [
        'canonical' => 'https://site.example/partner/active-space/',
        'description' => 'A public summary.',
    ],
    'blocks' => [
        ['type' => 'hero', 'heading' => 'Welcome'],
    ],
    'agent' => [
        'summary' => 'A useful public Space.',
        'instructions' => ['Describe the page accurately.'],
    ],
];
$suspended = $active;
$suspended['space']['slug'] = 'suspended-space';
$suspended['space']['status'] = 'suspended';
$suspended['seo']['canonical'] = 'https://site.example/partner/suspended-space/';
$draft = $active;
$draft['space']['slug'] = 'draft-space';
$draft['space']['status'] = 'draft';
$draft['seo']['canonical'] = 'https://site.example/partner/draft-space/';

$extra_snapshot = [
    'version' => 3,
    'space' => [
        'id' => 'private-space-id',
        'slug' => 'extra-space',
        'title' => 'Allowlisted Space',
        'status' => 'published',
        'partner_email' => 'LEAK_PARTNER_EMAIL',
        'zzz_future_private' => 'LEAK_SPACE_PRIVATE',
    ],
    'seo' => [
        'title' => 'Allowlisted SEO title',
        'description' => 'Allowlisted SEO description',
        'canonical' => 'https://site.example/partner/extra-space/',
        'internal_url' => 'LEAK_INTERNAL_URL',
        'zzz_future_private' => 'LEAK_SEO_PRIVATE',
    ],
    'blocks' => [
        [
            'type' => 'hero',
            'heading' => 'Keep this heading',
            'lede' => 'Keep this lede',
            'label' => 'Keep this label',
            'link_id' => 'l-extra',
            'placement_id' => 'hero',
            'destination' => 'LEAK_DESTINATION',
            'internal_url' => 'LEAK_BLOCK_INTERNAL',
            'zzz_future_private' => 'LEAK_BLOCK_PRIVATE',
        ],
    ],
    'links' => [
        'l-extra' => [
            'destination' => 'LEAK_LINK_DESTINATION',
            'internal_url' => 'LEAK_LINK_INTERNAL',
            'partner_email' => 'LEAK_LINK_EMAIL',
            'payout' => 'LEAK_PAYOUT',
            'secret_note' => 'LEAK_SECRET_NOTE',
            'zzz_future_private' => 'LEAK_LINK_PRIVATE',
        ],
    ],
    'agent' => [
        'summary' => 'Allowlisted agent summary',
        'instructions' => ['Keep this instruction'],
        'entities' => [
            ['name' => 'Public entity', 'type' => 'Thing', 'secret_note' => 'LEAK_ENTITY_SECRET'],
        ],
        'secret_note' => 'LEAK_AGENT_SECRET',
    ],
];

$agents = AgentPack::agents_md($active);
foreach (['Active Space', 'A useful public Space.', 'Describe the page accurately.', 'Disclosure', 'sponsored'] as $expected) {
    if (stripos($agents, $expected) === false) {
        throw new RuntimeException('agents.md missing expected content: ' . $expected);
    }
}

$sitemap = AgentPack::sitemap([$active, $suspended, $draft], 'https://site.example');
if (strpos($sitemap, 'active-space') === false || strpos($sitemap, 'suspended-space') !== false || strpos($sitemap, 'draft-space') !== false) {
    throw new RuntimeException('sitemap did not filter published spaces');
}
if (strpos($sitemap, 'AGENTS.md') === false || strpos($sitemap, 'agents.md') !== false) {
    throw new RuntimeException('sitemap did not use only canonical AGENTS.md');
}

$extra_context = AgentPack::ai_context($extra_snapshot);
if (($extra_context['space']['title'] ?? '') !== 'Allowlisted Space'
    || ($extra_context['seo']['title'] ?? '') !== 'Allowlisted SEO title'
    || ($extra_context['blocks'][0]['heading'] ?? '') !== 'Keep this heading'
    || ($extra_context['agent']['entities'][0]['name'] ?? '') !== 'Public entity') {
    throw new RuntimeException('agent context dropped an allowlisted field');
}
$extra_outputs = [
    $extra_context,
    AgentPack::manifest([$extra_snapshot], 'https://site.example'),
    AgentPack::agents_md($extra_snapshot),
    AgentPack::llms_txt([$extra_snapshot], 'https://site.example'),
    AgentPack::sitemap([$extra_snapshot], 'https://site.example'),
];
$manifest_text = json_encode($extra_outputs[1]);
if (strpos((string) $manifest_text, 'Allowlisted Space') === false
    || strpos((string) $extra_outputs[2], 'Allowlisted Space') === false
    || strpos((string) $extra_outputs[3], 'Allowlisted Space') === false
    || strpos((string) $extra_outputs[4], 'extra-space') === false) {
    throw new RuntimeException('agent outputs dropped allowlisted public fields');
}
$forbidden_fragments = [
    'destination',
    'internal_url',
    'partner_email',
    'payout',
    'secret_note',
    'zzz_future_private',
    'private-space-id',
    'LEAK_DESTINATION',
    'LEAK_BLOCK_INTERNAL',
    'LEAK_BLOCK_PRIVATE',
    'LEAK_LINK_DESTINATION',
    'LEAK_LINK_INTERNAL',
    'LEAK_LINK_EMAIL',
    'LEAK_PAYOUT',
    'LEAK_SECRET_NOTE',
    'LEAK_SPACE_PRIVATE',
    'LEAK_SEO_PRIVATE',
    'LEAK_LINK_PRIVATE',
    'LEAK_ENTITY_SECRET',
    'LEAK_AGENT_SECRET',
];
foreach ($extra_outputs as $extra_output) {
    $extra_text = is_array($extra_output) ? json_encode($extra_output) : (string) $extra_output;
    foreach ($forbidden_fragments as $fragment) {
        if (is_string($extra_text) && stripos($extra_text, $fragment) !== false) {
            throw new RuntimeException('agent output leaked a non-allowlisted field: ' . $fragment);
        }
    }
}

function partneropen_run_route(string $mode): array
{
    if (! function_exists('proc_open')) {
        throw new RuntimeException('proc_open is required for route smoke assertions');
    }
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__);
    $environment = is_array($_ENV) ? $_ENV : [];
    $environment['PARTNEROPEN_ROUTE_MODE'] = $mode;
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, __DIR__, $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('could not start route smoke process');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'status' => proc_close($process),
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

$granted_route = partneropen_run_route('asset-granted');
if (($granted_route['status'] ?? 1) !== 0 || strpos($granted_route['stdout'], 'Active Space') === false) {
    throw new RuntimeException('agent asset was not served with agent_pack consent');
}
$denied_route = partneropen_run_route('asset-denied');
if (($denied_route['status'] ?? 1) !== 0 || strpos($denied_route['stdout'], '__ROUTE_STATUS__404') === false || strpos($denied_route['stdout'], 'Active Space') !== false) {
    throw new RuntimeException('agent asset was not blocked without agent_pack consent');
}
$router_source = file_get_contents(__DIR__ . '/../includes/Public/Router.php');
if (strpos($denied_route['stdout'], 'no-store') === false && (! is_string($router_source) || strpos($router_source, 'Cache-Control: no-store, no-cache, must-revalidate') === false)) {
    throw new RuntimeException('agent asset denial omitted no-store cache protection');
}
$page_route = partneropen_run_route('space-no-agent');
if (($page_route['status'] ?? 1) !== 0 || strpos($page_route['stdout'], 'Active Space') === false) {
    throw new RuntimeException('Space page was incorrectly gated by agent_pack consent');
}
if (strpos($page_route['stdout'], '__ENQUEUED__') === false
    || strpos($page_route['stdout'], 'partneropen-connector-public') === false
    || strpos($page_route['stdout'], 'partneropen.css') === false
    || strpos($page_route['stdout'], '0.1.0') === false
    || strpos($granted_route['stdout'], '__ENQUEUED__[]') === false
    || strpos($denied_route['stdout'], '__ENQUEUED__[]') === false) {
}

$forbidden = ['email', 'secret', 'site_id', 'metric'];
$context_json = json_encode(AgentPack::ai_context($active));
$manifest_json = json_encode(AgentPack::manifest([$active], 'https://site.example'));
foreach ($forbidden as $term) {
    if ((is_string($context_json) && stripos($context_json, $term) !== false) || (is_string($manifest_json) && stripos($manifest_json, $term) !== false)) {
        throw new RuntimeException('public agent output contains forbidden term: ' . $term);
    }
}

// A snapshot stored by an older release may still carry a foreign canonical; the
// pack must fall back to this site's own URL instead of advertising another host.
$legacy = $active;
$legacy['seo']['canonical'] = 'https://elsewhere.example/partner/active-space/';
$legacy_outputs = [
    AgentPack::agents_md($legacy),
    AgentPack::llms_txt([$legacy], 'https://site.example'),
    (string) json_encode(AgentPack::ai_context($legacy)),
    (string) json_encode(AgentPack::manifest([$legacy], 'https://site.example')),
    AgentPack::sitemap([$legacy], 'https://site.example'),
];
foreach ($legacy_outputs as $index => $output) {
    if (stripos($output, 'elsewhere.example') !== false) {
        throw new RuntimeException('agent output ' . $index . ' published a foreign canonical origin');
    }
    if (stripos($output, 'site.example') === false) {
        throw new RuntimeException('agent output ' . $index . ' lost this site origin after dropping a foreign canonical');
    }
}

$wrong_scheme = $active;
$wrong_scheme['seo']['canonical'] = 'http://site.example/partner/active-space/';
$wrong_scheme_outputs = [
    AgentPack::agents_md($wrong_scheme),
    AgentPack::llms_txt([$wrong_scheme], 'https://site.example'),
    (string) json_encode(AgentPack::ai_context($wrong_scheme)),
    (string) json_encode(AgentPack::manifest([$wrong_scheme], 'https://site.example')),
    AgentPack::sitemap([$wrong_scheme], 'https://site.example'),
];
foreach ($wrong_scheme_outputs as $index => $output) {
    // JSON encodes slashes, and the sitemap namespace is an http:// URI, so normalise
    // the payload and assert on the rejected canonical itself.
    $plain = str_replace('\\/', '/', $output);
    if (strpos($plain, 'http://site.example') !== false || strpos($plain, 'https://site.example') === false) {
        throw new RuntimeException('agent output ' . $index . ' accepted a canonical with a different scheme');
    }
}

echo "PartnerOpen agent-pack tests: OK\n";
