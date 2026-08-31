<?php

declare(strict_types=1);

$transients = [];
function get_transient(string $key): mixed
{
    global $transients;
    return $transients[$key]['value'] ?? false;
}
function set_transient(string $key, mixed $value, int $expiration): bool
{
    global $transients;
    $transients[$key] = ['value' => $value, 'expires' => time() + $expiration];
    return true;
}

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Http/Signature.php';

use PartnerOpen\Connector\Http\Signature;

$secret = 'secret-value';
$body = '{"ok":true}';
$timestamp = time();
$nonce = 'nonce-round-trip';
$headers = [
    'X-PartnerOpen-Site' => 'site-1',
    'X-PartnerOpen-Timestamp' => (string) $timestamp,
    'X-PartnerOpen-Nonce' => $nonce,
    'X-PartnerOpen-Signature' => Signature::sign('POST', '/partneropen/v1/status', $timestamp, $nonce, $body, $secret),
    'X-PartnerOpen-Scopes' => 'cloud_connection',
];
if (! Signature::verify('POST', '/partneropen/v1/status', $headers, $body, $secret)) {
    throw new RuntimeException('valid signature should verify');
}
if (Signature::verify('POST', '/partneropen/v1/status', $headers, '{"ok":false}', $secret)) {
    throw new RuntimeException('tampered body should fail');
}
$expired_timestamp = time() - 301;
$expired_nonce = 'nonce-expired';
$expired_headers = [
    'X-PartnerOpen-Timestamp' => (string) $expired_timestamp,
    'X-PartnerOpen-Nonce' => $expired_nonce,
    'X-PartnerOpen-Signature' => Signature::sign('POST', '/partneropen/v1/status', $expired_timestamp, $expired_nonce, $body, $secret),
];
if (Signature::verify('POST', '/partneropen/v1/status', $expired_headers, $body, $secret)) {
    throw new RuntimeException('expired signature should fail');
}

$oversized_nonce = str_repeat('n', 129);
$oversized_timestamp = time();
$oversized_headers = [
    'X-PartnerOpen-Timestamp' => (string) $oversized_timestamp,
    'X-PartnerOpen-Nonce' => $oversized_nonce,
    'X-PartnerOpen-Signature' => Signature::sign('POST', '/partneropen/v1/status', $oversized_timestamp, $oversized_nonce, $body, $secret),
];
if (Signature::verify('POST', '/partneropen/v1/status', $oversized_headers, $body, $secret)) {
    throw new RuntimeException('a nonce longer than 128 characters should fail');
}
if (array_key_exists('partneropen_connector_nonce_' . $oversized_nonce, $transients)) {
    throw new RuntimeException('an oversized nonce should be rejected before transient lookup');
}
if (Signature::verify('POST', '/partneropen/v1/status', $headers, $body, $secret)) {
    throw new RuntimeException('replayed nonce should fail');
}

echo "PartnerOpen signature tests: OK\n";
