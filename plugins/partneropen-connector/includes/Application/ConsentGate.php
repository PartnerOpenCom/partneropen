<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Application;

use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;

final class ConsentGate
{
    public function register(): void
    {
        if (function_exists('add_filter')) {
            add_filter('pre_http_request', [$this, 'filter'], 10, 3);
        }
    }

    public static function allows(string $scope): bool
    {
        return Consent::granted($scope);
    }

    public function filter(mixed $pre, array $args, string $url): mixed
    {
        if (array_key_exists('partneropen_scope', $args)) {
            if (! self::allows('cloud_connection')) {
                return self::error('Outbound request requires cloud connection consent.');
            }
            $scope = is_string($args['partneropen_scope']) ? trim($args['partneropen_scope']) : '';
            if ($scope === '' || ! self::allows($scope)) {
                return self::error('Outbound request requires the granted consent scope: ' . ($scope !== '' ? $scope : 'a valid scope') . '.');
            }
        }

        $cloud_base = Options::connection()['cloud_base'] ?? '';
        $cloud_host = is_string($cloud_base) ? self::host($cloud_base) : '';
        $request_host = self::host($url);
        if ($cloud_host !== '' && $request_host !== '' && hash_equals($cloud_host, $request_host) && ! self::allows('cloud_connection')) {
            return self::error('Outbound request to PartnerOpen Cloud requires cloud connection consent.');
        }

        return $pre;
    }

    private static function host(string $url): string
    {
        $parts = wp_parse_url($url);
        if (! is_array($parts)) {
            return '';
        }

        return strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
    }

    private static function error(string $message): mixed
    {
        if (class_exists('WP_Error')) {
            return new \WP_Error('partneropen_consent_required', $message);
        }

        return false;
    }
}
