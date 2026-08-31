<?php

declare(strict_types=1);

$options = [];
$filters = [];
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
function add_filter(string $tag, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
    global $filters;
    $filters[$tag] = $callback;
}
class WP_Error
{
    public function __construct(public string $code, public string $message)
    {
    }
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Infrastructure/Options.php';
require_once __DIR__ . '/../includes/Domain/Consent.php';
require_once __DIR__ . '/../includes/Application/ConsentGate.php';

use PartnerOpen\Connector\Application\ConsentGate;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;

$options = Options::defaults();
Options::save_connection(['cloud_base' => 'https://cloud.example.test']);
$gate = new ConsentGate();
$gate->register();
$blocked = $gate->filter(null, ['partneropen_scope' => 'content_sync'], 'https://example.test/sync');
if (! $blocked instanceof WP_Error || $blocked->code !== 'partneropen_consent_required') {
    throw new RuntimeException('scoped request should be blocked without consent');
}
Consent::grant(['content_sync'], '1');
$payload_only = $gate->filter(null, ['partneropen_scope' => 'content_sync'], 'https://example.test/sync');
if (! $payload_only instanceof WP_Error || $payload_only->code !== 'partneropen_consent_required') {
    throw new RuntimeException('payload consent without cloud consent should be blocked');
}
Consent::grant(['cloud_connection'], '1');
if ($gate->filter(null, ['partneropen_scope' => 'content_sync'], 'https://example.test/sync') !== null) {
    throw new RuntimeException('marked request should pass when cloud and payload consent are granted');
}
Consent::revoke('cloud_connection');
$cloud_blocked = $gate->filter(null, [], 'https://cloud.example.test/api/status');
if (! $cloud_blocked instanceof WP_Error || $cloud_blocked->code !== 'partneropen_consent_required') {
    throw new RuntimeException('cloud request should be blocked without cloud consent');
}

echo "PartnerOpen consent gate tests: OK\n";
