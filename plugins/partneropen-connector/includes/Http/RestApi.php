<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Http;

use PartnerOpen\Connector\Application\ClickCounter;
use PartnerOpen\Connector\Application\Pairing;
use PartnerOpen\Connector\Application\SnapshotStore;
use PartnerOpen\Connector\Application\SpaceRegistry;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;
use PartnerOpen\Connector\Support\Validation;

final class RestApi
{
    private const NAMESPACE = 'partneropen/v1';
    /** @var string[] */
    public const DEFAULT_CLOUD_HOSTS = [
        'partneropen.com',
        'www.partneropen.com',
    ];

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('rest_api_init', [$this, 'register_routes']);
        }
    }

    public function register_routes(): void
    {
        if (! function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/pair', [
            'methods' => 'POST',
            'callback' => [self::class, 'pair'],
            'permission_callback' => [self::class, 'pair_permission'],
        ]);
        register_rest_route(self::NAMESPACE, '/status', [
            'methods' => 'GET',
            'callback' => [self::class, 'status'],
            'permission_callback' => static fn (mixed $request): mixed => self::permission('cloud_connection', 'GET', $request),
        ]);
        register_rest_route(self::NAMESPACE, '/spaces', [
            'methods' => 'GET',
            'callback' => [self::class, 'spaces'],
            'permission_callback' => static fn (mixed $request): mixed => self::permission('cloud_connection', 'GET', $request),
        ]);
        register_rest_route(self::NAMESPACE, '/spaces/(?P<space>[a-z0-9-]{2,64})/snapshot', [
            'methods' => 'PUT',
            'callback' => [self::class, 'snapshot'],
            'permission_callback' => static fn (mixed $request): mixed => self::permission('content_sync', 'PUT', $request),
        ]);
        register_rest_route(self::NAMESPACE, '/spaces/(?P<space>[a-z0-9-]{2,64})/suspend', [
            'methods' => 'POST',
            'callback' => static fn (mixed $request): mixed => self::suspend($request),
            'permission_callback' => static fn (mixed $request): mixed => self::permission('cloud_connection', 'POST', $request),
        ]);
        register_rest_route(self::NAMESPACE, '/spaces/(?P<space>[a-z0-9-]{2,64})/resume', [
            'methods' => 'POST',
            'callback' => static fn (mixed $request): mixed => self::resume($request),
            'permission_callback' => static fn (mixed $request): mixed => self::permission('cloud_connection', 'POST', $request),
        ]);
        register_rest_route(self::NAMESPACE, '/metrics', [
            'methods' => 'GET',
            'callback' => [self::class, 'metrics'],
            'permission_callback' => static fn (mixed $request): mixed => self::permission('aggregate_metrics', 'GET', $request),
        ]);
        register_rest_route(self::NAMESPACE, '/disconnect', [
            'methods' => 'POST',
            'callback' => [self::class, 'disconnect'],
            'permission_callback' => static fn (mixed $request): mixed => self::permission('cloud_connection', 'POST', $request),
        ]);
    }

    public static function authorize(string $method, string $path, array $headers, string $body, string $scope): bool
    {
        return self::authorization_error($method, $path, $headers, $body, $scope) === null;
    }

    /**
     * Return the REST error code for an authorization failure, or null when
     * the request is authorized. This keeps the decision independently
     * testable without constructing a WP_REST_Request.
     *
     * @param array<string, mixed> $headers
     */
    public static function authorization_error(
        string $method,
        string $path,
        array $headers,
        string $body,
        string $scope,
    ): ?string {
        if (! in_array($scope, Consent::SCOPES, true)) {
            return 'partneropen_signature_invalid';
        }
        $connection = Options::connection();
        if (($connection['status'] ?? '') !== 'connected') {
            return 'partneropen_connection_required';
        }
        if (! Consent::granted($scope)) {
            return 'partneropen_consent_required';
        }

        $site_id = self::header($headers, 'x-partneropen-site');
        $stored_site_id = trim((string) ($connection['site_id'] ?? ''));
        if ($site_id === '' || $stored_site_id === '' || ! hash_equals($stored_site_id, $site_id)) {
            return 'partneropen_signature_invalid';
        }

        $declared_scopes = self::header($headers, 'x-partneropen-scopes');
        $scopes = array_map(
            static fn (string $declared): string => strtolower(trim($declared)),
            explode(',', $declared_scopes),
        );
        if ($declared_scopes === '' || ! in_array(strtolower($scope), $scopes, true)) {
            return 'partneropen_consent_required';
        }

        $secret = Options::secret();
        if ($secret === '' || ! Signature::verify($method, self::canonical_path($path), $headers, $body, $secret)) {
            return 'partneropen_signature_invalid';
        }

        return null;
    }

    public static function pair_permission(mixed $request): mixed
    {
        if (! Consent::granted('cloud_connection')) {
            return self::error('partneropen_consent_required', 'Cloud connection consent is required.', 403);
        }
        $body = self::body($request);
        $code = is_string($body['code'] ?? null) ? trim($body['code']) : '';
        if ($code === '' || ! Pairing::valid($code)) {
            return self::error('partneropen_pairing_invalid', 'The pairing code is missing or expired.', 403);
        }

        return true;
    }

    public static function pair(mixed $request): mixed
    {
        $body = self::body($request);
        if (! Consent::granted('cloud_connection')) {
            return self::error('partneropen_consent_required', 'Cloud connection consent is required.', 403);
        }

        $cloud_base = is_scalar($body['cloud_base'] ?? null)
            ? trim((string) $body['cloud_base'])
            : '';
        $cloud_parts = self::parse_url_parts($cloud_base);
        $cloud_host = is_array($cloud_parts) ? strtolower(rtrim((string) ($cloud_parts['host'] ?? ''), '.')) : '';
        if ($cloud_host === '' || Validation::https_url($cloud_base, self::cloud_hosts()) === null) {
            return self::error('partneropen_invalid_cloud_base', 'cloud_base must be an HTTPS URL on an approved PartnerOpen Cloud host.', 400);
        }

        $connection = Options::connection();
        $site_id = (string) ($connection['site_id'] ?? '');
        if ($site_id === '') {
            $site_id = function_exists('wp_generate_uuid4')
                ? (string) wp_generate_uuid4()
                : bin2hex(random_bytes(16));
        }
        $partner_email = (string) ($connection['partner_email'] ?? '');
        if (array_key_exists('partner_email', $body)) {
            $candidate = is_scalar($body['partner_email'] ?? null)
                ? Validation::email((string) $body['partner_email'])
                : null;
            if ($candidate === null) {
                return self::error('partneropen_invalid_email', 'partner_email must be a valid email address.', 400);
            }
            if (Consent::granted('partner_email')) {
                $partner_email = $candidate;
            }
        }
        $code = is_string($body['code'] ?? null) ? trim($body['code']) : '';

        // Validate every request field before consuming the one-time code so a
        // malformed cloud_base or email leaves the code usable for a retry.
        if ($code === '' || ! Pairing::consume($code)) {
            return self::error('partneropen_pairing_invalid', 'The pairing code is missing or expired.', 403);
        }

        $prefix = Validation::prefix((string) ($connection['prefix'] ?? 'partner')) ?? 'partner';
        $policy_version = (string) ($connection['policy_version'] ?? '1');
        $secret = Pairing::rotate_secret();
        Options::save_connection([
            'status' => 'connected',
            'site_id' => $site_id,
            'prefix' => $prefix,
            'partner_email' => $partner_email,
            'cloud_base' => rtrim($cloud_base, '/'),
            'policy_version' => $policy_version,
            'paired_at' => self::now(),
            'disconnected_at' => 0,
        ]);

        return [
            'site_id' => $site_id,
            'secret' => $secret,
            'scopes' => self::granted_scopes(),
            'prefix' => $prefix,
            'policy_version' => $policy_version,
        ];
    }

    public static function status(mixed $request = null): array
    {
        $connection = Options::connection();
        $spaces = [];
        foreach (SpaceRegistry::all() as $space) {
            $spaces[] = [
                'id' => (string) ($space['id'] ?? ''),
                'slug' => (string) ($space['slug'] ?? ''),
                'status' => (string) ($space['status'] ?? 'draft'),
                'snapshot_version' => (int) ($space['snapshot_version'] ?? 0),
                'published_at' => (int) ($space['published_at'] ?? 0),
            ];
        }

        return [
            'status' => (string) ($connection['status'] ?? 'local'),
            'prefix' => (string) ($connection['prefix'] ?? 'partner'),
            'owner_paused' => Options::paused(),
            'spaces' => $spaces,
            'scopes' => self::granted_scopes(),
            'connector_version' => defined('PARTNEROPEN_CONNECTOR_VERSION') ? PARTNEROPEN_CONNECTOR_VERSION : '0.1.0',
            'last_sync_at' => (int) ($connection['last_sync_at'] ?? 0),
        ];
    }

    public static function spaces(mixed $request = null): array
    {
        $spaces = [];
        foreach (SpaceRegistry::all() as $space) {
            $spaces[] = [
                'id' => (string) ($space['id'] ?? ''),
                'slug' => (string) ($space['slug'] ?? ''),
                'title' => (string) ($space['title'] ?? ''),
                'status' => (string) ($space['status'] ?? 'draft'),
                'snapshot_version' => (int) ($space['snapshot_version'] ?? 0),
                'published_at' => (int) ($space['published_at'] ?? 0),
            ];
        }

        return ['spaces' => $spaces];
    }

    public static function snapshot(mixed $request): mixed
    {
        $space_key = self::param($request, 'space');
        $body = self::body($request);
        if ($body === []) {
            return self::error('partneropen_invalid_snapshot', 'A snapshot object is required.', 400);
        }
        if (array_key_exists('version', $body) && $body['version'] !== SnapshotStore::FORMAT_VERSION) {
            return self::error('partneropen_unsupported_snapshot_version', 'This connector supports snapshot format version 3.', 400);
        }
        if (! array_key_exists('version', $body)) {
            $body['version'] = SnapshotStore::FORMAT_VERSION;
        }
        $errors = array_values(SnapshotStore::validate($body));
        if ($errors !== []) {
            return self::error('partneropen_invalid_snapshot', 'The snapshot does not match the required format.', 400, [
                'errors' => $errors,
            ]);
        }

        $space = self::resolve_space($space_key);
        $space_id = is_array($space) ? (string) ($space['id'] ?? '') : '';
        $created = false;
        if ($space_id === '') {
            $slug = Validation::space_slug($space_key);
            if ($slug === null) {
                return self::error('partneropen_invalid_snapshot', 'The space slug is invalid.', 400);
            }
            if (count(SpaceRegistry::all()) >= SpaceRegistry::MAX_SPACES) {
                return self::error('partneropen_space_limit', 'The maximum number of spaces has been reached.', 409);
            }
            $snapshot_space = is_array($body['space'] ?? null) ? $body['space'] : [];
            $title = Validation::text($snapshot_space['title'] ?? '');
            $space_id = 'space-' . $slug;
            try {
                SpaceRegistry::save([
                    'id' => $space_id,
                    'slug' => $slug,
                    'title' => $title !== '' ? $title : $slug,
                    'status' => 'draft',
                    'snapshot_version' => 0,
                    'published_at' => 0,
                ]);
            } catch (\RuntimeException $exception) {
                return self::error('partneropen_space_limit', $exception->getMessage(), 409);
            }
            $created = true;
        }

        try {
            SnapshotStore::put($space_id, $body);
        } catch (\RuntimeException $exception) {
            return self::error('partneropen_invalid_snapshot', $exception->getMessage(), 400);
        }
        Options::save_connection(['last_sync_at' => self::now()]);

        return self::response([
            'space' => SpaceRegistry::find($space_id),
            'snapshot' => SnapshotStore::get($space_id),
        ], $created ? 201 : 200);
    }

    public static function suspend(mixed $request): mixed
    {
        $space = self::resolve_space(self::param($request, 'space'));
        $space_id = is_array($space) ? (string) ($space['id'] ?? '') : '';
        if ($space_id === '') {
            return self::error('partneropen_space_not_found', 'Space not found.', 404);
        }
        SpaceRegistry::suspend($space_id);

        return ['space' => SpaceRegistry::find($space_id)];
    }

    public static function resume(mixed $request): mixed
    {
        $space = self::resolve_space(self::param($request, 'space'));
        $space_id = is_array($space) ? (string) ($space['id'] ?? '') : '';
        if ($space_id === '') {
            return self::error('partneropen_space_not_found', 'Space not found.', 404);
        }
        SpaceRegistry::resume($space_id);

        return ['space' => SpaceRegistry::find($space_id)];
    }

    public static function metrics(mixed $request = null): array
    {
        return [
            'clicks' => ClickCounter::all(),
            'retention_days' => ClickCounter::RETENTION_DAYS,
        ];
    }

    public static function disconnect(mixed $request = null): array
    {
        Options::save_connection([
            'status' => 'disconnected',
            'disconnected_at' => self::now(),
        ]);
        Pairing::revoke_secret();
        Consent::revoke_all();

        return ['status' => 'disconnected'];
    }

    private static function permission(string $scope, string $method, mixed $request): mixed
    {
        $headers = self::headers($request);
        $body = self::raw_body($request);
        $path = self::request_path($request);
        $failure = self::authorization_error($method, $path, $headers, $body, $scope);
        if ($failure === 'partneropen_connection_required') {
            return self::error('partneropen_connection_required', 'The connector is not connected.', 403);
        }
        if ($failure === 'partneropen_consent_required') {
            return self::error('partneropen_consent_required', 'The required consent scope is not granted.', 403);
        }
        if ($failure !== null) {
            return self::error('partneropen_signature_invalid', 'The request signature is invalid or expired.', 401);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private static function body(mixed $request): array
    {
        if (is_array($request)) {
            if (is_array($request['body'] ?? null)) {
                return $request['body'];
            }
            if (is_string($request['body'] ?? null)) {
                $decoded = json_decode($request['body'], true);
                return is_array($decoded) ? $decoded : [];
            }
            return $request;
        }
        if (is_object($request) && method_exists($request, 'get_json_params')) {
            $json = $request->get_json_params();
            if (is_array($json)) {
                return $json;
            }
        }
        $raw = self::raw_body($request);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function raw_body(mixed $request): string
    {
        if (is_array($request)) {
            if (is_string($request['raw_body'] ?? null)) {
                return $request['raw_body'];
            }
            if (is_string($request['body_raw'] ?? null)) {
                return $request['body_raw'];
            }
            return is_string($request['body'] ?? null) ? $request['body'] : '';
        }
        if (is_object($request) && method_exists($request, 'get_body')) {
            $body = $request->get_body();
            return is_string($body) ? $body : '';
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private static function headers(mixed $request): array
    {
        if (is_array($request) && is_array($request['headers'] ?? null)) {
            return $request['headers'];
        }
        if (is_object($request) && method_exists($request, 'get_headers')) {
            $headers = $request->get_headers();
            if (is_array($headers)) {
                return $headers;
            }
        }

        $headers = [];
        if (is_object($request) && method_exists($request, 'get_header')) {
            foreach (['site', 'timestamp', 'nonce', 'signature', 'scopes'] as $name) {
                $value = $request->get_header('X-PartnerOpen-' . ucfirst($name));
                if (is_string($value) && $value !== '') {
                    $headers['X-PartnerOpen-' . ucfirst($name)] = $value;
                }
            }
        }

        return $headers;
    }

    private static function request_path(mixed $request): string
    {
        if (is_array($request) && is_string($request['path'] ?? null)) {
            return self::canonical_path($request['path']);
        }
        if (is_object($request) && method_exists($request, 'get_route')) {
            $route = $request->get_route();
            if (is_string($route) && $route !== '') {
                return self::canonical_path($route);
            }
        }

        return '/partneropen/v1/status';
    }

    private static function canonical_path(string $path): string
    {
        $query_position = strpos($path, '?');
        if ($query_position !== false) {
            $path = substr($path, 0, $query_position);
        }
        if (str_starts_with($path, '/wp-json')) {
            $path = (string) substr($path, strlen('/wp-json'));
        }
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    private static function param(mixed $request, string $name): string
    {
        if (is_array($request)) {
            return trim((string) ($request[$name] ?? ''));
        }
        if (! is_object($request)) {
            return '';
        }

        // Route placeholders must win over same-named body fields: WP_REST_Request
        // resolves JSON body parameters before URL parameters.
        if (method_exists($request, 'get_url_params')) {
            $url_params = $request->get_url_params();
            if (is_array($url_params) && isset($url_params[$name]) && is_scalar($url_params[$name])) {
                return trim((string) $url_params[$name]);
            }
        }
        if (method_exists($request, 'get_param')) {
            $value = $request->get_param($name);
            if (is_scalar($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }
    /**
     * @return array<string, mixed>|null
     */
    private static function resolve_space(string $key): ?array
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }
        $space = SpaceRegistry::find($key);
        if ($space !== null) {
            return $space;
        }

        return SpaceRegistry::find_by_slug($key);
    }

    /**
     * @param array<string, mixed> $headers
     */
    private static function header(array $headers, string $name): string
    {
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower(str_replace('_', '-', (string) $key)) === $name) {
                if (is_array($value)) {
                    $value = reset($value);
                }

                return is_scalar($value) ? trim((string) $value) : '';
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private static function granted_scopes(): array
    {
        $scopes = [];
        foreach (Consent::SCOPES as $scope) {
            if (Consent::granted($scope)) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    /**
     * @return string[]
     */
    private static function cloud_hosts(): array
    {
        $hosts = self::DEFAULT_CLOUD_HOSTS;
        if (defined('PARTNEROPEN_CONNECTOR_CLOUD_HOSTS')) {
            $configured = constant('PARTNEROPEN_CONNECTOR_CLOUD_HOSTS');
            if (is_string($configured)) {
                $hosts = Validation::normalize_hosts($configured);
            }
        }
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('partneropen_connector_cloud_hosts', $hosts);
            if (is_array($filtered) || is_string($filtered)) {
                $hosts = $filtered;
            }
        }

        return Validation::normalize_hosts($hosts);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parse_url_parts(string $url): ?array
    {
        $parts = wp_parse_url($url);

        return is_array($parts) ? $parts : null;
    }

    private static function now(): int
    {
        return function_exists('current_time') ? (int) current_time('timestamp', true) : time();
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function response(array $data, int $status): mixed
    {
        if (class_exists('WP_REST_Response')) {
            return new \WP_REST_Response($data, $status);
        }

        return $data;
    }

    private static function error(string $code, string $message, int $status, array $extra = []): mixed
    {
        if (function_exists('__')) {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- $message is one of RestApi's fixed REST error literals or a controlled validation error.
            $message = (string) __($message, 'partneropen-connector');
        }
        if (class_exists('WP_Error')) {
            return new \WP_Error($code, $message, array_merge(['status' => $status], $extra));
        }

        return false;
    }
}
