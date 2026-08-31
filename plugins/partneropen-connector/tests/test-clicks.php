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
function current_time(string $type, bool $gmt = false): string|int
{
    return $type === 'Y-m-d' ? '2026-08-24' : strtotime('2026-08-24 12:00:00 UTC');
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Support/Validation.php';
require_once __DIR__ . '/../includes/Infrastructure/Options.php';
require_once __DIR__ . '/../includes/Domain/Consent.php';
require_once __DIR__ . '/../includes/Application/ClickCounter.php';

use PartnerOpen\Connector\Application\ClickCounter;
use PartnerOpen\Connector\Domain\Consent;

$options['partneropen_clicks'] = [
    '2026-08-24' => ['hero' => 2],
];
ClickCounter::record('hero');
$all = ClickCounter::all();
if (($all['2026-08-24']['hero'] ?? 0) !== 2) {
    throw new RuntimeException('clicks must not record without aggregate_metrics consent');
}

Consent::grant(['aggregate_metrics'], '1');
ClickCounter::record('hero');
$all = ClickCounter::all();
if (($all['2026-08-24']['hero'] ?? 0) !== 3) {
    throw new RuntimeException('clicks should aggregate after aggregate_metrics consent');
}
if (array_key_exists('request', $all) || array_key_exists('ip', $all) || array_key_exists('user_agent', $all)) {
    throw new RuntimeException('clicks must not contain request metadata');
}

// 2026-05-27 through 2026-08-24 is exactly 90 calendar days inclusive.
$options['partneropen_clicks'] = [
    '2026-05-27' => ['oldest-kept' => 1],
    '2026-05-26' => ['outside-retention' => 1],
    '2026-08-24' => ['hero' => 3],
];
ClickCounter::prune_scheduled();
$all = ClickCounter::all();
if (! isset($all['2026-05-27']) || isset($all['2026-05-26'])) {
    throw new RuntimeException('retention should keep exactly 90 days');
}
if (($all['2026-08-24']['hero'] ?? 0) !== 3) {
    throw new RuntimeException('prune_scheduled should not require a new click');
}

echo "PartnerOpen click counter tests: OK\n";
