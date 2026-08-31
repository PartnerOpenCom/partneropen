<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Application;

use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;

final class Pairing
{
    private const CODE_TRANSIENT = 'partneropen_connector_pair_code';
    private const CODE_TTL = 900;
    private static ?string $fallback_code = null;

    public static function issue_code(): string
    {
        if (! Consent::granted('cloud_connection')) {
            throw new \RuntimeException('Cloud connection consent is required before pairing.');
        }

        $code = function_exists('wp_generate_password')
            ? (string) wp_generate_password(12, false, false)
            : strtoupper(substr(bin2hex(random_bytes(8)), 0, 12));
        if (strlen($code) !== 12) {
            $code = strtoupper(substr(hash('sha256', $code . microtime(true)), 0, 12));
        }

        if (function_exists('set_transient')) {
            set_transient(self::CODE_TRANSIENT, $code, self::CODE_TTL);
        } else {
            self::$fallback_code = $code;
        }

        return $code;
    }

    public static function valid(string $code): bool
    {
        $stored = self::stored_code();

        return is_string($stored) && $stored !== '' && $code !== '' && hash_equals($stored, $code);
    }

    public static function consume(string $code): bool
    {
        if (! self::valid($code)) {
            return false;
        }

        if (function_exists('delete_transient')) {
            delete_transient(self::CODE_TRANSIENT);
        } else {
            self::$fallback_code = null;
        }

        return true;
    }

    private static function stored_code(): mixed
    {
        if (! function_exists('get_transient')) {
            return self::$fallback_code;
        }

        return get_transient(self::CODE_TRANSIENT);
    }

    public static function rotate_secret(): string
    {
        try {
            $secret = bin2hex(random_bytes(32));
        } catch (\Throwable) {
            $generated = function_exists('wp_generate_password')
                ? (string) wp_generate_password(64, true, true)
                : hash('sha256', uniqid('', true));
            $secret = strtolower(preg_replace('/[^a-f0-9]/i', '', $generated) ?? '');
            $secret = str_pad(substr($secret, 0, 64), 64, '0');
        }

        Options::set_secret($secret);

        return $secret;
    }

    public static function revoke_secret(): void
    {
        Options::set_secret('');
    }
}
