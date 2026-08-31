<?php

declare(strict_types=1);

require_once __DIR__ . '/compat.php';
require_once __DIR__ . '/../includes/Support/Validation.php';

use PartnerOpen\Connector\Support\Validation;

if (Validation::prefix('wp-admin') !== null || Validation::prefix('partneropen') !== null) {
    throw new RuntimeException('reserved prefixes should be rejected');
}
if (Validation::prefix('person@example.com') !== null || Validation::prefix('5555555555') !== null) {
    throw new RuntimeException('PII-like prefixes should be rejected');
}
if (Validation::prefix('Partner-Page') !== 'partner-page') {
    throw new RuntimeException('valid prefixes should normalize to lowercase');
}
if (Validation::https_url('http://dest.example/path', ['dest.example']) !== null) {
    throw new RuntimeException('HTTP destinations should be rejected');
}
if (Validation::https_url('https://user:pass@dest.example/path', ['dest.example']) !== null) {
    throw new RuntimeException('userinfo destinations should be rejected');
}
if (Validation::https_url('javascript:alert(1)', ['dest.example']) !== null) {
    throw new RuntimeException('javascript destinations should be rejected');
}
if (Validation::https_url('https://dest.example/path', ['dest.example']) !== 'https://dest.example/path') {
    throw new RuntimeException('allowlisted HTTPS destination should pass');
}
$rich = Validation::rich_text('<p>Hello <a href="https://evil.example">world</a></p><script>alert(1)</script><strong>safe</strong>');
if (str_contains($rich, '<a') || str_contains($rich, '<script') || str_contains($rich, 'alert(1)')) {
    throw new RuntimeException('rich text should strip anchors and scripts');
}
if (! str_contains($rich, '<p>') || ! str_contains($rich, '<strong>')) {
    throw new RuntimeException('rich text should retain approved tags');
}
if (Validation::text(" \0<b>clean</b>\r ") !== 'clean') {
    throw new RuntimeException('plain text validation should strip tags and control characters');
}
if (Validation::normalize_hosts(['HTTPS://Dest.Example:443', 'dest.example.']) !== ['dest.example']) {
    throw new RuntimeException('host normalization should preserve its existing canonical result');
}

echo "PartnerOpen validation tests: OK\n";
