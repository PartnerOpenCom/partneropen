<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Support;

final class Validation
{
    /**
     * @param string $value
     */
    public static function prefix(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) < 2 || strlen($value) > 32) {
            return null;
        }
        if (preg_match('/^[a-z0-9-]+$/', $value) !== 1) {
            return null;
        }
        if (self::is_reserved($value) || self::is_pii_like($value)) {
            return null;
        }

        return $value;
    }

    public static function space_slug(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) < 2 || strlen($value) > 64) {
            return null;
        }
        if (preg_match('/^[a-z0-9-]+$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    public static function email(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '' || strpbrk($value, "\r\n") !== false) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? $value : null;
    }

    /**
     * @param string[] $allowed_hosts
     */
    public static function https_url(string $url, array $allowed_hosts): ?string
    {
        $url = trim($url);
        if ($url === '' || strpbrk($url, "\r\n\0") !== false) {
            return null;
        }
        if (preg_match('/^(?:javascript|data):/i', $url) === 1) {
            return null;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = self::wp_parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }
        if (empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = self::normalize_host((string) $parts['host']);
        if ($host === '' || ! in_array($host, self::normalize_hosts($allowed_hosts), true)) {
            return null;
        }

        return $url;
    }

    public static function text(mixed $value): string
    {
        if (is_array($value) || is_object($value) || is_resource($value)) {
            return '';
        }

        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        $value = str_replace(["\0", "\r"], ['', ''], $value);

        return trim(self::wp_strip_all_tags($value));
    }

    public static function rich_text(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? '';
        $html = preg_replace('/<\s*(script|style|iframe|object|embed)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? '';
        $html = preg_replace('/<\s*(script|style|iframe|object|embed)\b[^>]*\/?\s*>/is', '', $html) ?? '';

        if (function_exists('wp_kses')) {
            $html = (string) wp_kses($html, [
                'p' => [],
                'br' => [],
                'strong' => [],
                'em' => [],
                'ul' => [],
                'ol' => [],
                'li' => [],
                'h2' => [],
                'h3' => [],
                'blockquote' => [],
            ]);
        } else {
            $html = preg_replace_callback(
                '/<\/?\s*([a-z0-9]+)(?:\s+[^>]*)?>/i',
                static function (array $matches): string {
                    $tag = strtolower($matches[1]);
                    $allowed = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'blockquote'];
                    if (! in_array($tag, $allowed, true)) {
                        return '';
                    }
                    $closing = str_starts_with($matches[0], '</') ? '</' : '<';

                    return $closing . $tag . '>';
                },
                $html,
            ) ?? '';
        }

        return trim($html);
    }

    /**
     * @param array<int, string>|string $hosts
     * @return string[]
     */
    public static function normalize_hosts(array|string $hosts): array
    {
        if (is_string($hosts)) {
            $hosts = preg_split('/[\s,]+/', $hosts) ?: [];
        }

        $normalized = [];
        foreach ($hosts as $host) {
            $host = self::normalize_host((string) $host);
            if ($host !== '') {
                $normalized[$host] = true;
            }
        }

        return array_keys($normalized);
    }

    private static function is_reserved(string $value): bool
    {
        return in_array($value, [
            'wp-admin',
            'wp-content',
            'wp-includes',
            'wp-json',
            'feed',
            'sitemap',
            'robots',
            'login',
            'admin',
            'partneropen',
        ], true);
    }

    private static function is_pii_like(string $value): bool
    {
        if (str_contains($value, '@') || preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $value) === 1) {
            return true;
        }

        return preg_match('/\d{7,}/', $value) === 1;
    }

    private static function normalize_host(string $host): string
    {
        $host = strtolower(trim($host));
        if (str_contains($host, '://')) {
            $parsed = self::wp_parse_url($host);
            $host = is_array($parsed) ? (string) ($parsed['host'] ?? '') : '';
        }
        if (str_contains($host, ':')) {
            $host = (string) preg_replace('/:\d+$/', '', $host);
        }

        return rtrim($host, '.');
    }

    private static function wp_strip_all_tags(string $value): string
    {
        return (string) wp_strip_all_tags($value);
    }

    /**
     * @return array<string, mixed>|false
     */
    private static function wp_parse_url(string $url): array|false
    {
        $parts = wp_parse_url($url);

        return is_array($parts) ? $parts : false;
    }
}
