<?php

declare(strict_types=1);

$GLOBALS['partneropen_test_options'] = [];
$GLOBALS['partneropen_test_transients'] = [];

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
        if (array_key_exists($key, $GLOBALS['partneropen_test_options'])) {
            return false;
        }
        $GLOBALS['partneropen_test_options'][$key] = $value;
        return true;
    }
}
if (! function_exists('delete_option')) {
    function delete_option(string $key): bool
    {
        unset($GLOBALS['partneropen_test_options'][$key]);
        return true;
    }
}
if (! function_exists('set_transient')) {
    function set_transient(string $key, mixed $value, int $expiration): bool
    {
        $GLOBALS['partneropen_test_transients'][$key] = $value;
        return true;
    }
}
if (! function_exists('get_transient')) {
    function get_transient(string $key): mixed
    {
        return $GLOBALS['partneropen_test_transients'][$key] ?? false;
    }
}
if (! function_exists('delete_transient')) {
    function delete_transient(string $key): bool
    {
        unset($GLOBALS['partneropen_test_transients'][$key]);
        return true;
    }
}
if (! function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false): int|string
    {
        if ($type === 'timestamp') {
            return time();
        }
        if ($type === 'Y-m-d') {
            return gmdate('Y-m-d');
        }
        return gmdate($type);
    }
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Domain/Link.php';
require_once __DIR__ . '/../includes/Domain/Block.php';
require_once __DIR__ . '/../includes/Support/Validation.php';
require_once __DIR__ . '/../includes/Domain/Consent.php';
require_once __DIR__ . '/../includes/Infrastructure/Options.php';
require_once __DIR__ . '/../includes/Application/SpaceRegistry.php';
require_once __DIR__ . '/../includes/Application/SnapshotStore.php';
require_once __DIR__ . '/../includes/Application/ClickCounter.php';
require_once __DIR__ . '/../includes/Public/LinkResolver.php';

use PartnerOpen\Connector\Application\ClickCounter;
use PartnerOpen\Connector\Application\SnapshotStore;
use PartnerOpen\Connector\Application\SpaceRegistry;
use PartnerOpen\Connector\Infrastructure\Options;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Public\LinkResolver;

Options::save_spaces([
    'space-1' => [
        'id' => 'space-1',
        'slug' => 'reviews',
        'title' => 'Public Reviews',
        'status' => 'published',
        'snapshot_version' => 0,
        'published_at' => 0,
    ],
]);
SnapshotStore::put('space-1', [
    'version' => 3,
    'space' => [
        'id' => 'space-1',
        'slug' => 'reviews',
        'title' => 'Public Reviews',
        'status' => 'published',
    ],
    'allowed_hosts' => ['dest.example'],
    'blocks' => [],
    'links' => [
        'active' => [
            'destination' => 'https://dest.example/path',
            'status' => 'active',
            'placements' => ['hero'],
        ],
        'inactive' => [
            'destination' => 'https://dest.example/inactive',
            'status' => 'inactive',
            'placements' => ['hero'],
        ],
        'bad-host' => [
            'destination' => 'https://other.example/path',
            'status' => 'active',
            'placements' => ['hero'],
        ],
    ],
]);
// Simulate a legacy snapshot whose destination must be re-validated at resolve time.
$stored_snapshot = get_option('partneropen_snapshot_space-1', []);
$stored_snapshot['links']['bad-host'] = [
    'destination' => 'https://other.example/path',
    'status' => 'active',
    'placements' => ['hero'],
];
update_option('partneropen_snapshot_space-1', $stored_snapshot, false);

Options::set_paused(true);
$paused = LinkResolver::resolve('active', 'hero');
if ($paused !== ['status' => 404, 'reason' => 'paused']) {
    throw new RuntimeException('paused resolver response was not exact');
}
Options::set_paused(false);

Consent::revoke('affiliate_service');
$consent_denied = LinkResolver::resolve('active', 'hero');
if ($consent_denied !== ['status' => 404, 'reason' => 'consent']) {
    throw new RuntimeException('resolver did not enforce affiliate consent');
}
Consent::grant(['affiliate_service', 'aggregate_metrics'], '1');

SpaceRegistry::suspend('space-1');
$suspended = LinkResolver::resolve('active', 'hero');
if ($suspended !== ['status' => 404, 'reason' => 'suspended']) {
    throw new RuntimeException('suspended space was not blocked');
}
SpaceRegistry::resume('space-1');

$space_one = SpaceRegistry::find('space-1');
Options::save_spaces([
    'space-1' => is_array($space_one) ? $space_one : [
        'id' => 'space-1',
        'slug' => 'reviews',
        'title' => 'Public Reviews',
        'status' => 'published',
    ],
    'space-2' => [
        'id' => 'space-2',
        'slug' => 'suspended-reviews',
        'title' => 'Suspended Reviews',
        'status' => 'suspended',
        'snapshot_version' => 0,
        'published_at' => 0,
    ],
]);
SnapshotStore::put('space-2', [
    'version' => 3,
    'space' => [
        'id' => 'space-2',
        'slug' => 'suspended-reviews',
        'title' => 'Suspended Reviews',
        'status' => 'suspended',
    ],
    'allowed_hosts' => ['dest.example'],
    'blocks' => [],
    'links' => [
        'active' => [
            'destination' => 'https://dest.example/suspended',
            'status' => 'active',
            'placements' => ['hero'],
        ],
    ],
]);
$reused = LinkResolver::resolve('active', 'hero');
if ($reused !== ['status' => 404, 'reason' => 'suspended']) {
    throw new RuntimeException('suspended duplicate link resolved through another Space');
}
$spaces = Options::spaces();
unset($spaces['space-2']);
Options::save_spaces($spaces);

$cases = [
    ['missing', 'hero', 'unknown_link'],
    ['inactive', 'hero', 'inactive'],
    ['active', 'wrong-placement', 'placement'],
    ['bad-host', 'hero', 'destination'],
];
foreach ($cases as [$link_id, $placement_id, $reason]) {
    $result = LinkResolver::resolve($link_id, $placement_id);
    if ($result !== ['status' => 404, 'reason' => $reason]) {
        throw new RuntimeException('resolver reason mismatch for ' . $link_id);
    }
}

$valid = LinkResolver::resolve('active', 'hero');
if ($valid !== ['status' => 302, 'target' => 'https://dest.example/path']) {
    throw new RuntimeException('valid resolver response was not exact');
}
$clicks = ClickCounter::all();
$total = 0;
foreach ($clicks as $day) {
    if (is_array($day)) {
        foreach ($day as $count) {
            $total += (int) $count;
        }
    }
}
if ($total !== 1) {
    throw new RuntimeException('valid resolver did not increment exactly one counter');
}

echo "PartnerOpen resolver tests: OK\n";
