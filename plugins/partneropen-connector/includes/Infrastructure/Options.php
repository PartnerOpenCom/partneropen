<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Infrastructure;

final class Options
{
    private const CONNECTION_KEY = 'partneropen_connection';
    private const CONSENT_KEY = 'partneropen_consent';
    private const SECRET_KEY = 'partneropen_secret';
    private const PAUSE_KEY = 'partneropen_pause';
    private const SPACES_KEY = 'partneropen_spaces';
    private const CLICKS_KEY = 'partneropen_clicks';

    /**
     * @return array<string, mixed>
     */
    public static function connection(): array
    {
        $defaults = self::defaults()[self::CONNECTION_KEY];
        $stored = self::get(self::CONNECTION_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        $connection = array_merge($defaults, $stored);
        $connection['status'] = in_array($connection['status'] ?? '', ['local', 'connected', 'disconnected'], true)
            ? (string) $connection['status']
            : 'local';
        foreach (['site_id', 'prefix', 'partner_email', 'cloud_base', 'policy_version'] as $key) {
            $connection[$key] = is_scalar($connection[$key] ?? null) ? (string) $connection[$key] : (string) $defaults[$key];
        }
        foreach (['paired_at', 'disconnected_at', 'last_sync_at'] as $key) {
            $connection[$key] = max(0, (int) ($connection[$key] ?? 0));
        }

        return $connection;
    }

    /**
     * Merge a connection patch without discarding fields that a newer connector knows about.
     *
     * @param array<string, mixed> $patch
     */
    public static function save_connection(array $patch): void
    {
        $connection = array_merge(self::connection(), $patch);
        $connection['status'] = in_array($connection['status'] ?? '', ['local', 'connected', 'disconnected'], true)
            ? (string) $connection['status']
            : self::connection()['status'];
        foreach (['site_id', 'prefix', 'partner_email', 'cloud_base', 'policy_version'] as $key) {
            if (array_key_exists($key, $connection)) {
                $connection[$key] = is_scalar($connection[$key]) ? (string) $connection[$key] : '';
            }
        }
        foreach (['paired_at', 'disconnected_at', 'last_sync_at'] as $key) {
            if (array_key_exists($key, $connection)) {
                $connection[$key] = max(0, (int) $connection[$key]);
            }
        }

        self::update(self::CONNECTION_KEY, $connection);
    }

    public static function secret(): string
    {
        $secret = self::get(self::SECRET_KEY, '');

        return is_scalar($secret) ? (string) $secret : '';
    }

    public static function set_secret(string $secret): void
    {
        if (function_exists('add_option') && self::get(self::SECRET_KEY, null) === null) {
            if (add_option(self::SECRET_KEY, $secret, '', false)) {
                return;
            }
        }

        self::update(self::SECRET_KEY, $secret, false);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function consent(): array
    {
        $stored = self::get(self::CONSENT_KEY, []);
        if (! is_array($stored)) {
            return [];
        }

        $consent = [];
        foreach ($stored as $scope => $record) {
            if (! is_string($scope) || ! is_array($record)) {
                continue;
            }
            $consent[$scope] = [
                ...$record,
                'granted' => (bool) ($record['granted'] ?? false),
                'policy_version' => is_scalar($record['policy_version'] ?? null) ? (string) $record['policy_version'] : '',
                'accepted_at' => max(0, (int) ($record['accepted_at'] ?? 0)),
                'revoked_at' => max(0, (int) ($record['revoked_at'] ?? 0)),
            ];
        }

        return $consent;
    }

    /**
     * @param array<string, array<string, mixed>> $consent
     */
    public static function save_consent(array $consent): void
    {
        self::update(self::CONSENT_KEY, $consent);
    }

    public static function paused(): bool
    {
        $pause = self::get(self::PAUSE_KEY, []);

        return is_array($pause) && (bool) ($pause['owner_paused'] ?? false);
    }

    public static function set_paused(bool $paused): void
    {
        self::update(self::PAUSE_KEY, [
            'owner_paused' => $paused,
            'changed_at' => self::now(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function spaces(): array
    {
        $stored = self::get(self::SPACES_KEY, []);
        if (! is_array($stored)) {
            return [];
        }

        $spaces = [];
        foreach ($stored as $id => $space) {
            if (! is_array($space)) {
                continue;
            }
            $key = is_string($id) && $id !== '' ? $id : (string) ($space['id'] ?? '');
            if ($key === '') {
                continue;
            }
            $spaces[$key] = $space;
        }

        return $spaces;
    }

    /**
     * @param array<string, array<string, mixed>> $spaces
     */
    public static function save_spaces(array $spaces): void
    {
        self::update(self::SPACES_KEY, $spaces);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            self::CONNECTION_KEY => [
                'status' => 'local',
                'site_id' => '',
                'prefix' => 'partner',
                'partner_email' => '',
                'cloud_base' => '',
                'policy_version' => '1',
                'paired_at' => 0,
                'disconnected_at' => 0,
                'last_sync_at' => 0,
            ],
            self::CONSENT_KEY => [],
            self::SECRET_KEY => '',
            self::PAUSE_KEY => [
                'owner_paused' => false,
                'changed_at' => 0,
            ],
            self::SPACES_KEY => [],
            self::CLICKS_KEY => [],
        ];
    }

    public static function delete_all(): void
    {
        foreach (self::spaces() as $space) {
            $id = (string) ($space['id'] ?? '');
            if ($id !== '' && function_exists('delete_option')) {
                delete_option('partneropen_snapshot_' . $id);
            }
        }

        foreach (array_keys(self::defaults()) as $key) {
            if (function_exists('delete_option')) {
                delete_option($key);
            }
        }

        if (function_exists('delete_transient')) {
            delete_transient('partneropen_connector_pair_code');
        }

        global $wpdb;
        if (! isset($wpdb) || ! is_object($wpdb)
            || ! method_exists($wpdb, 'prepare') || ! method_exists($wpdb, 'query')) {
            return;
        }

        if (! isset($wpdb->options) || ! is_string($wpdb->options) || $wpdb->options === '') {
            return;
        }
        if (! method_exists($wpdb, 'esc_like')) {
            return;
        }

        $prefixes = [
            'partneropen_snapshot_',
            '_transient_partneropen_connector_',
            '_transient_timeout_partneropen_connector_',
            '_transient_partneropen_connector_pair_code',
            '_transient_timeout_partneropen_connector_pair_code',
            '_transient_partneropen_connector_nonce_',
            '_transient_timeout_partneropen_connector_nonce_',
        ];
        foreach ($prefixes as $prefix) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off uninstall cleanup; no cache exists to invalidate.
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $wpdb->esc_like($prefix) . '%'
                )
            );
        }
    }

    private static function get(string $key, mixed $default): mixed
    {
        if (! function_exists('get_option')) {
            return $default;
        }

        $value = get_option($key, $default);

        return $value === false ? $default : $value;
    }

    private static function update(string $key, mixed $value, bool $autoload = true): void
    {
        if (function_exists('update_option')) {
            update_option($key, $value, $autoload);
        }
    }

    private static function now(): int
    {
        return function_exists('current_time') ? (int) current_time('timestamp', true) : time();
    }
}
