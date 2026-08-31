<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Http;

final class Signature
{
    private const NONCE_TRANSIENT_PREFIX = 'partneropen_connector_nonce_';

    /** @var array<string, int> */
    private static array $fallback_nonces = [];

    public static function sign(
        string $method,
        string $path,
        int $timestamp,
        string $nonce,
        string $body,
        string $secret,
    ): string {
        $canonical = self::canonical($method, $path, $timestamp, $nonce, $body);

        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * @param array<string, mixed> $headers
     */
    public static function verify(
        string $method,
        string $path,
        array $headers,
        string $body,
        string $secret,
    ): bool {
        $timestamp = self::header($headers, 'x-partneropen-timestamp');
        $nonce = self::header($headers, 'x-partneropen-nonce');
        $signature = self::header($headers, 'x-partneropen-signature');
        if ($timestamp === '' || $nonce === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return false;
        }
        if (strlen($nonce) > 128) {
            return false;
        }

        $timestamp_value = (int) $timestamp;
        if (abs(time() - $timestamp_value) > 300) {
            return false;
        }

        $expected = self::sign($method, $path, $timestamp_value, $nonce, $body, $secret);
        if (! hash_equals(strtolower($expected), strtolower($signature))) {
            return false;
        }
        if (self::nonce_seen($nonce)) {
            return false;
        }
        self::remember_nonce($nonce);

        return true;
    }

    private static function canonical(string $method, string $path, int $timestamp, string $nonce, string $body): string
    {
        $query_position = strpos($path, '?');
        if ($query_position !== false) {
            $path = substr($path, 0, $query_position);
        }
        if ($path === '') {
            $path = '/';
        }

        return strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
    }

    /**
     * @param array<string, mixed> $headers
     */
    private static function header(array $headers, string $name): string
    {
        $normalized_name = strtolower($name);
        foreach ($headers as $key => $value) {
            $key = strtolower(str_replace('_', '-', (string) $key));
            if ($key === $normalized_name || $key === 'http-' . $normalized_name) {
                if (is_array($value)) {
                    $value = reset($value);
                }

                return is_scalar($value) ? trim((string) $value) : '';
            }
        }

        return '';
    }

    private static function nonce_seen(string $nonce): bool
    {
        if (function_exists('get_transient')) {
            return get_transient(self::NONCE_TRANSIENT_PREFIX . $nonce) !== false;
        }

        $now = time();
        foreach (self::$fallback_nonces as $known => $expires) {
            if ($expires <= $now) {
                unset(self::$fallback_nonces[$known]);
            }
        }

        return isset(self::$fallback_nonces[$nonce]);
    }
    private static function remember_nonce(string $nonce): void
    {
        if (function_exists('set_transient')) {
            set_transient(self::NONCE_TRANSIENT_PREFIX . $nonce, 1, 600);
            return;
        }

        self::$fallback_nonces[$nonce] = time() + 600;
    }
}
