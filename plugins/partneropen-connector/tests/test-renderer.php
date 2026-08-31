<?php

declare(strict_types=1);

$GLOBALS['partneropen_test_options'] = [];
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
if (! function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://site.example' . ($path === '' ? '/' : '/' . ltrim($path, '/'));
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (! function_exists('esc_attr')) {
    function esc_attr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (! function_exists('esc_url')) {
    function esc_url(string $value): string
    {
        return preg_match('/^javascript:|^data:/i', trim($value)) === 1
            ? ''
            : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}
if (! function_exists('wp_kses')) {
    function wp_kses(string $html, array $allowed_html): string
    {
        return strip_tags($html, '<p><br><strong><em><ul><ol><li><h2><h3><blockquote>');
    }
}
if (! function_exists('wp_kses_post')) {
    function wp_kses_post(string $html): string
    {
        return wp_kses($html, []);
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
require_once __DIR__ . '/../includes/Public/SnapshotRenderer.php';
use PartnerOpen\Connector\Public\SnapshotRenderer;
use PartnerOpen\Connector\Domain\Consent;

$snapshot = [
    'version' => 3,
    'space' => [
        'id' => 'space-1',
        'slug' => 'reviews',
        'title' => 'Public Reviews',
        'status' => 'published',
    ],
    'allowed_hosts' => ['dest.example'],
    'links' => [
        'l1' => [
            'destination' => 'https://dest.example/path',
            'label' => 'Open destination',
            'disclosure' => 'Sponsored destination',
            'status' => 'active',
            'placements' => ['hero'],
        ],
        'l2' => [
            'destination' => 'https://dest.example/card',
            'label' => 'Card destination',
            'disclosure' => 'Card disclosure',
            'status' => 'active',
            'placements' => ['card-1'],
        ],
    ],
    'blocks' => [
        [
            'type' => 'hero',
            'heading' => 'A safe page',
            'lede' => 'Read the public summary.',
            'link_id' => 'l1',
            'placement_id' => 'hero',
            'label' => 'Open destination',
        ],
        [
            'type' => 'text',
            'html' => '<p>Allowed <strong>text</strong>.</p><script>alert(1)</script><a href="https://dest.example/raw">raw destination</a>',
        ],
        [
            'type' => 'cards',
            'items' => [
                [
                    'title' => 'Valid card',
                    'body' => 'Card body',
                    'link_id' => 'l2',
                    'placement_id' => 'card-1',
                    'label' => 'Open card',
                ],
                [
                    'title' => 'Wrong placement card',
                    'body' => 'This card has no usable link.',
                    'link_id' => 'l2',
                    'placement_id' => 'wrong-placement',
                    'label' => 'Should not render',
                ],
            ],
        ],
        [
            'type' => 'unknown',
            'label' => 'Drop this block',
        ],
        [
            'type' => 'image',
            'url' => 'https://site.example/assets/local.png',
            'alt' => 'Local image',
        ],
        [
            'type' => 'image',
            'url' => 'https://remote.example/assets/remote.png',
            'alt' => 'Remote image',
        ],
    ],
];

Consent::revoke('affiliate_service');
$denied_html = SnapshotRenderer::render($snapshot, '/partneropen/go');
if (strpos($denied_html, 'Open destination') === false || strpos($denied_html, 'href="/partneropen/go') !== false || strpos($denied_html, 'rel="sponsored nofollow noopener"') !== false) {
    throw new RuntimeException('renderer did not emit plain-text labels without affiliate consent');
}

Consent::grant(['affiliate_service'], '1');
$html = SnapshotRenderer::render($snapshot, '/partneropen/go');
foreach ([
    'href="/partneropen/go/l1/hero"',
    'href="/partneropen/go/l2/card-1"',
    'rel="sponsored nofollow noopener"',
    'Sponsored destination',
    'Goes to dest.example',
    'partneropen-space__disclosure',
    'src="https://site.example/assets/local.png"',
] as $expected) {
    if (strpos($html, $expected) === false) {
        throw new RuntimeException('renderer missing expected output: ' . $expected);
    }
}
if (strpos($html, 'href="https://dest.example') !== false) {
    throw new RuntimeException('renderer exposed a raw destination href');
}
if (strpos($html, 'https://remote.example/assets/remote.png') !== false || strpos($html, 'src="https://remote.example') !== false) {
    throw new RuntimeException('renderer emitted a remote image URL');
}
if (strpos($html, '<script>alert(1)</script>') !== false || strpos($html, 'alert(1)') !== false || strpos($html, 'href="https://dest.example/raw"') !== false) {
    throw new RuntimeException('text.html retained unsafe markup');
}
if (strpos($html, 'Drop this block') !== false || strpos($html, 'Should not render') !== false) {
    throw new RuntimeException('renderer retained an unknown or incorrectly placed link');
}

echo "PartnerOpen renderer tests: OK\n";
