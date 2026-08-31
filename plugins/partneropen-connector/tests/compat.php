<?php
/**
 * Standalone compatibility shims.
 *
 * The plugin's classes call WordPress escaping, sanitizing and URL helpers directly so
 * that output is verifiably escaped at every call site. The repository also runs those
 * classes as plain PHP scripts (see tests/test-*.php) where WordPress is not loaded, so
 * this file defines faithful fallbacks for the small set of pure helpers involved. Every
 * declaration is guarded: under WordPress none of these are ever defined here.
 */

declare(strict_types=1);

if (defined('ABSPATH')) {
    return;
}

if (! function_exists('esc_html')) {
    function esc_html(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_url')) {
    function esc_url(mixed $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || preg_match('/[\r\n\t\0]/', $url) === 1) {
            return '';
        }
        if (preg_match('#^(?:https?:|/|\#|\?)#i', $url) !== 1) {
            return '';
        }

        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_xml')) {
    function esc_xml(mixed $text): string
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        unset($domain);

        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html(__($text, $domain));
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return esc_attr(__($text, $domain));
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(mixed $text, bool $remove_breaks = false): string
    {
        $text = (string) $text;
        $text = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $text);
        $text = strip_tags($text);
        if ($remove_breaks) {
            $text = (string) preg_replace('/[\r\n\t ]+/', ' ', $text);
        }

        return trim($text);
    }
}

if (! function_exists('wp_parse_url')) {
    function wp_parse_url(string $url, int $component = -1): mixed
    {
        return parse_url($url, $component);
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(mixed $value): string
    {
        $value = wp_strip_all_tags($value);
        $value = (string) preg_replace('/[\r\n\t\0\x0B]+/', ' ', $value);

        return trim((string) preg_replace('/ {2,}/', ' ', $value));
    }
}

if (! function_exists('wp_unslash')) {
    function wp_unslash(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (! function_exists('wp_kses_post')) {
    function wp_kses_post(mixed $content): string
    {
        $content = (string) $content;

        return (string) preg_replace(
            '@<(script|style|iframe|object|embed)[^>]*?>.*?</\\1>@si',
            '',
            $content
        );
    }
}
