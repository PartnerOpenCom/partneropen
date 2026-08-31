<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Application;

use PartnerOpen\Connector\Domain\Block;
use PartnerOpen\Connector\Domain\Link;
use PartnerOpen\Connector\Support\Validation;

final class SnapshotStore
{
    public const FORMAT_VERSION = 3;

    /** @var array<string, array<string, mixed>> */
    private static array $memory = [];

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $space_id): ?array
    {
        $key = 'partneropen_snapshot_' . trim($space_id);
        if (function_exists('get_option')) {
            $snapshot = get_option($key, null);
            return is_array($snapshot) ? $snapshot : null;
        }

        return self::$memory[$space_id] ?? null;
    }

    /**
     * Validate a canonical snapshot against docs/snapshot-schema.json.
     *
     * @param array<string, mixed> $snapshot
     * @return string[] Human-readable field-path errors.
     */
    public static function validate(array $snapshot): array
    {
        $errors = [];
        $allowed = ['version', 'space', 'seo', 'allowed_hosts', 'blocks', 'links', 'agent'];
        foreach (array_keys($snapshot) as $key) {
            if (! in_array((string) $key, $allowed, true)) {
                self::error($errors, (string) $key, 'is not a permitted snapshot member');
            }
        }

        foreach (['version', 'space', 'blocks', 'links'] as $required) {
            if (! array_key_exists($required, $snapshot)) {
                self::error($errors, $required, 'is required');
            }
        }

        if (array_key_exists('version', $snapshot)
            && (! is_int($snapshot['version']) || $snapshot['version'] !== self::FORMAT_VERSION)) {
            self::error($errors, 'version', 'must be 3');
        }

        if (array_key_exists('space', $snapshot)) {
            self::validate_space($snapshot['space'], $errors);
        }
        if (array_key_exists('seo', $snapshot)) {
            self::validate_seo($snapshot['seo'], $errors);
        }
        if (array_key_exists('allowed_hosts', $snapshot)) {
            self::validate_allowed_hosts($snapshot['allowed_hosts'], $errors);
        }
        if (array_key_exists('blocks', $snapshot)) {
            self::validate_blocks($snapshot['blocks'], $errors);
        }
        if (array_key_exists('links', $snapshot)) {
            $allowed_hosts = is_array($snapshot['allowed_hosts'] ?? null)
                ? $snapshot['allowed_hosts']
                : [];
            self::validate_links($snapshot['links'], $allowed_hosts, $errors);
        }
        if (self::directory_build()) {
            if (is_array($snapshot['links'] ?? null) && $snapshot['links'] !== []) {
                self::error($errors, 'links', 'affiliate links are disabled in the directory build');
            }
            foreach ((array) ($snapshot['blocks'] ?? []) as $index => $block) {
                if (! is_array($block)) {
                    continue;
                }
                if (isset($block['link_id']) || in_array((string) ($block['type'] ?? ''), ['link', 'cta'], true)) {
                    self::error($errors, 'blocks[' . $index . ']', 'affiliate links are disabled in the directory build');
                }
                foreach ((array) ($block['items'] ?? []) as $item_index => $item) {
                    if (is_array($item) && isset($item['link_id'])) {
                        self::error($errors, 'blocks[' . $index . '].items[' . $item_index . ']', 'affiliate links are disabled in the directory build');
                    }
                }
            }
        }

        return $errors;
    }

    private static function directory_build(): bool
    {
        return defined('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD')
            && PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD === true;
    }


    /**
     * @param array<string, mixed> $snapshot
     */
    public static function put(string $space_id, array $snapshot): void
    {
        $space_id = trim($space_id);
        if ($space_id === '') {
            throw new \RuntimeException('A space id is required.');
        }
        if (array_key_exists('version', $snapshot) && $snapshot['version'] !== self::FORMAT_VERSION) {
            throw new \RuntimeException('Unsupported snapshot version.');
        }

        $space_data = is_array($snapshot['space'] ?? null) ? $snapshot['space'] : [];
        $existing_space = SpaceRegistry::find($space_id);
        $slug_source = is_array($existing_space) && isset($existing_space['slug'])
            ? (string) $existing_space['slug']
            : (string) ($space_data['slug'] ?? $space_id);
        $slug = Validation::space_slug($slug_source);
        if ($slug === null) {
            throw new \RuntimeException('Snapshot contains an invalid space slug.');
        }
        $title = self::limited_text($space_data['title'] ?? ($existing_space['title'] ?? ''), 200);
        $allowed_hosts = self::normalize_allowed_hosts($snapshot['allowed_hosts'] ?? []);
        $next_version = max(0, (int) ($existing_space['snapshot_version'] ?? 0)) + 1;
        $registry_status = is_array($existing_space) && ($existing_space['status'] ?? '') === 'suspended'
            ? 'suspended'
            : 'published';

        $normalized = [
            'version' => self::FORMAT_VERSION,
            'space' => [
                'id' => $space_id,
                'slug' => $slug,
                'title' => $title,
                // The registry status is authoritative for visibility. The snapshot itself
                // is always stored publishable so resume() restores a working Space.
                'status' => 'published',
            ],
            'seo' => self::normalize_seo($snapshot['seo'] ?? []),
            'allowed_hosts' => $allowed_hosts,
            'blocks' => self::normalize_blocks($snapshot['blocks'] ?? [], $allowed_hosts),
            'links' => self::normalize_links($snapshot['links'] ?? [], $allowed_hosts),
        ];
        if (array_key_exists('agent', $snapshot) && is_array($snapshot['agent'])) {
            $normalized['agent'] = self::normalize_agent($snapshot['agent']);
        }

        SpaceRegistry::save([
            'id' => $space_id,
            'slug' => $slug,
            'title' => $title,
            'status' => $registry_status,
            'snapshot_version' => $next_version,
            'published_at' => self::now(),
        ]);

        $key = 'partneropen_snapshot_' . $space_id;
        if (function_exists('update_option')) {
            update_option($key, $normalized, false);
        } else {
            self::$memory[$space_id] = $normalized;
        }
    }

    public static function delete(string $space_id): void
    {
        $space_id = trim($space_id);
        if (function_exists('delete_option')) {
            delete_option('partneropen_snapshot_' . $space_id);
        }
        unset(self::$memory[$space_id]);
    }

    /**
     * Return the stored snapshot for every registered Space, regardless of status.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $snapshots = [];
        foreach (SpaceRegistry::all() as $id => $space) {
            $space_id = (string) ($space['id'] ?? $id);
            if ($space_id === '') {
                continue;
            }
            $snapshot = self::get($space_id);
            if (is_array($snapshot)) {
                $snapshots[$space_id] = $snapshot;
            }
        }

        return $snapshots;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function published(): array
    {
        $published = [];
        foreach (SpaceRegistry::all() as $id => $space) {
            if (($space['status'] ?? '') !== 'published') {
                continue;
            }
            $space_id = (string) ($space['id'] ?? $id);
            $snapshot = self::get($space_id);
            if (is_array($snapshot)) {
                $published[$space_id] = $snapshot;
            }
        }

        return $published;
    }

    /**
     * @param mixed $seo
     * @return array<string, string>
     */
    private static function normalize_seo(mixed $seo): array
    {
        $seo = is_array($seo) ? $seo : [];

        return [
            'title' => self::limited_text($seo['title'] ?? '', 160),
            'description' => self::limited_text($seo['description'] ?? '', 320),
            'canonical' => self::same_origin_canonical(self::limited_text($seo['canonical'] ?? '', 2000)),
        ];
    }

    /**
     * A partner-supplied canonical may only point at this site. A foreign origin is
     * dropped so the owner's domain never advertises another host in its own public
     * agent pack or sitemap; the agent pack then derives the URL from prefix and slug.
     */
    private static function same_origin_canonical(string $canonical): string
    {
        if ($canonical === '' || ! self::is_https_url($canonical)) {
            return '';
        }

        $home = function_exists('home_url') ? (string) home_url('/') : '';
        if ($home === '') {
            return '';
        }
        $home_parts = self::url_parts($home);
        $candidate = self::url_parts($canonical);
        if (! is_array($home_parts) || ! is_array($candidate)) {
            return '';
        }
        if (! self::same_origin_parts($candidate, $home_parts)) {
            return '';
        }

        return $canonical;
    }

    /**
     * @param mixed $blocks
     * @param string[] $allowed_hosts
     * @return array<int, array<string, mixed>>
     */
    private static function normalize_blocks(mixed $blocks, array $allowed_hosts): array
    {
        if (! is_array($blocks)) {
            return [];
        }

        $normalized = [];
        foreach ($blocks as $block_data) {
            if (! is_array($block_data)) {
                continue;
            }
            $type = is_string($block_data['type'] ?? null) ? $block_data['type'] : '';
            if (! in_array($type, Block::ALLOWED_TYPES, true)) {
                continue;
            }

            switch ($type) {
                case 'hero':
                    $normalized[] = [
                        'type' => 'hero',
                        'heading' => self::limited_text($block_data['heading'] ?? '', 200),
                        'lede' => self::limited_text($block_data['lede'] ?? '', 500),
                        'link_id' => self::limited_text($block_data['link_id'] ?? '', 64),
                        'placement_id' => self::limited_text($block_data['placement_id'] ?? '', 64),
                        'label' => self::limited_text($block_data['label'] ?? '', 160),
                    ];
                    break;
                case 'text':
                    $html = is_scalar($block_data['html'] ?? null) ? (string) $block_data['html'] : '';
                    $normalized[] = [
                        'type' => 'text',
                        'html' => self::limited_rich_text($html, 2000),
                    ];
                    break;
                case 'cards':
                    $items = [];
                    if (is_array($block_data['items'] ?? null)) {
                        foreach ($block_data['items'] as $item) {
                            if (! is_array($item)) {
                                continue;
                            }
                            $items[] = [
                                'title' => self::limited_text($item['title'] ?? '', 200),
                                'body' => self::limited_text($item['body'] ?? '', 1000),
                                'link_id' => self::limited_text($item['link_id'] ?? '', 64),
                                'placement_id' => self::limited_text($item['placement_id'] ?? '', 64),
                                'label' => self::limited_text($item['label'] ?? '', 160),
                            ];
                        }
                    }
                    $normalized[] = ['type' => 'cards', 'items' => $items];
                    break;
                case 'cta':
                case 'link':
                    $normalized[] = [
                        'type' => $type,
                        'label' => self::limited_text($block_data['label'] ?? '', 160),
                        'link_id' => self::limited_text($block_data['link_id'] ?? '', 64),
                        'placement_id' => self::limited_text($block_data['placement_id'] ?? '', 64),
                    ];
                    break;
                case 'faq':
                    $items = [];
                    if (is_array($block_data['items'] ?? null)) {
                        foreach ($block_data['items'] as $item) {
                            if (! is_array($item)) {
                                continue;
                            }
                            $items[] = [
                                'q' => self::limited_text($item['q'] ?? '', 300),
                                'a' => self::limited_text($item['a'] ?? '', 2000),
                            ];
                        }
                    }
                    $normalized[] = ['type' => 'faq', 'items' => $items];
                    break;
                case 'comparison':
                case 'table':
                    $columns = [];
                    if (is_array($block_data['columns'] ?? null)) {
                        foreach ($block_data['columns'] as $column) {
                            $columns[] = self::limited_text($column, 120);
                        }
                    }
                    $rows = [];
                    if (is_array($block_data['rows'] ?? null)) {
                        foreach ($block_data['rows'] as $row) {
                            if (! is_array($row)) {
                                continue;
                            }
                            $rows[] = array_values(array_map(
                                static fn (mixed $value): string => self::limited_text($value, 500),
                                $row,
                            ));
                        }
                    }
                    $normalized[] = ['type' => $type, 'columns' => $columns, 'rows' => $rows];
                    break;
                case 'image':
                    $url = is_scalar($block_data['url'] ?? null) ? (string) $block_data['url'] : '';
                    $normalized[] = [
                        'type' => 'image',
                        'url' => self::same_origin_image($url),
                        'alt' => self::limited_text($block_data['alt'] ?? '', 200),
                    ];
                    break;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $links
     * @param string[] $allowed_hosts
     * @return array<string, array<string, mixed>>
     */
    private static function normalize_links(mixed $links, array $allowed_hosts): array
    {
        if (! is_array($links)) {
            return [];
        }

        $normalized = [];
        foreach ($links as $link_id => $link_data) {
            $link_id = (string) $link_id;
            if (array_key_exists('destination', $link_data) && ! is_scalar($link_data['destination'])) {
                continue;
            }
            foreach (['label', 'disclosure', 'status'] as $field) {
                if (array_key_exists($field, $link_data) && ! is_scalar($link_data[$field])) {
                    continue 2;
                }
            }
            $link = Link::from_array($link_data, $allowed_hosts);
            if ($link === null) {
                continue;
            }
            $record = $link->to_array();
            $placements = [];
            foreach ($record['placements'] as $placement) {
                if (preg_match('/^[a-z0-9-]{1,64}$/', $placement) === 1) {
                    $placements[$placement] = true;
                }
            }
            $record['placements'] = array_keys($placements);
            $normalized[$link_id] = $record;
        }

        return $normalized;
    }

    /**
     * @param mixed $hosts
     * @return string[]
     */
    private static function normalize_allowed_hosts(mixed $hosts): array
    {
        if (! is_array($hosts)) {
            return [];
        }

        $strings = [];
        foreach ($hosts as $host) {
            if (is_string($host)) {
                $strings[] = $host;
            }
        }

        return Validation::normalize_hosts($strings);
    }

    /**
     * @param array<string, mixed> $agent
     * @return array<string, mixed>
     */
    private static function normalize_agent(array $agent): array
    {
        $normalized = [];
        if (array_key_exists('summary', $agent)) {
            $normalized['summary'] = self::limited_text($agent['summary'], 2000);
        }
        if (is_array($agent['instructions'] ?? null)) {
            $normalized['instructions'] = [];
            foreach ($agent['instructions'] as $instruction) {
                $normalized['instructions'][] = self::limited_text($instruction, 1000);
            }
        }
        if (is_array($agent['entities'] ?? null)) {
            $normalized['entities'] = [];
            foreach ($agent['entities'] as $entity) {
                if (! is_array($entity)) {
                    continue;
                }
                $normalized['entities'][] = [
                    'name' => self::limited_text($entity['name'] ?? '', 200),
                    'type' => self::limited_text($entity['type'] ?? '', 120),
                ];
            }
        }

        return $normalized;
    }

    private static function same_origin_image(string $url): string
    {
        if ($url === '' || ! self::is_https_url($url)) {
            return '';
        }
        $home = function_exists('home_url') ? (string) home_url('/') : '';
        if ($home === '') {
            return '';
        }
        $candidate = self::url_parts($url);
        $home_parts = self::url_parts($home);
        if (! is_array($candidate) || ! is_array($home_parts) || ! self::same_origin_parts($candidate, $home_parts)) {
            return '';
        }

        return trim($url);
    }

    private static function limited_text(mixed $value, int $max): string
    {
        return substr(Validation::text($value), 0, max(0, $max));
    }

    private static function limited_rich_text(string $value, int $max): string
    {
        return substr(Validation::rich_text($value), 0, max(0, $max));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function url_parts(string $url): ?array
    {
        $parts = wp_parse_url($url);

        return is_array($parts) ? $parts : null;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $home
     */
    private static function same_origin_parts(array $candidate, array $home): bool
    {
        if (strtolower((string) ($candidate['scheme'] ?? '')) !== strtolower((string) ($home['scheme'] ?? ''))) {
            return false;
        }
        if (strtolower((string) ($candidate['host'] ?? '')) !== strtolower((string) ($home['host'] ?? ''))) {
            return false;
        }
        if (isset($candidate['port']) !== isset($home['port'])) {
            return false;
        }
        if (isset($candidate['port']) && (int) $candidate['port'] !== (int) $home['port']) {
            return false;
        }
        if (isset($candidate['user']) || isset($candidate['pass'])) {
            return false;
        }

        return true;
    }

    /** @param string[] $errors */
    private static function error(array &$errors, string $path, string $message): void
    {
        $errors[] = $path . ' ' . self::translate($message);
    }

    private static function translate(string $message): string
    {
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- $message comes from SnapshotStore's fixed validator message set (required, permitted-member, type, length, URL, host, array, object, uniqueness, and status literals).
        return function_exists('__') ? (string) __($message, 'partneropen-connector') : $message;
    }

    /**
     * @param array<string> $required
     * @param array<string, mixed> $value
     * @param string[] $errors
     */
    private static function required(array $value, array $required, string $path, array &$errors): void
    {
        foreach ($required as $field) {
            if (! array_key_exists($field, $value)) {
                self::error($errors, $path . '.' . $field, 'is required');
            }
        }
    }

    /**
     * @param array<string, mixed> $value
     * @param string[] $errors
     */
    private static function reject_unknown(array $value, array $allowed, string $path, array &$errors): void
    {
        foreach (array_keys($value) as $key) {
            if (! in_array((string) $key, $allowed, true)) {
                self::error($errors, $path . '.' . (string) $key, 'is not a permitted member');
            }
        }
    }

    /**
     * @param string[] $errors
     */
    private static function string_field(mixed $value, string $path, array &$errors, int $min = 0, ?int $max = null): bool
    {
        if (! is_string($value)) {
            self::error($errors, $path, 'must be a string');
            return false;
        }
        $length = strlen($value);
        if ($length < $min) {
            self::error($errors, $path, $min === 1 ? 'must not be empty' : 'is shorter than allowed');
        }
        if ($max !== null && $length > $max) {
            self::error($errors, $path, 'exceeds the maximum length of ' . $max);
        }

        return true;
    }

    /** @param string[] $errors */
    private static function validate_space(mixed $space, array &$errors): void
    {
        if (! is_array($space) || ! self::is_object_array($space)) {
            self::error($errors, 'space', 'must be an object');
            return;
        }
        self::reject_unknown($space, ['id', 'slug', 'title', 'status'], 'space', $errors);
        // The Connector fills id and status while provisioning/syncing a Space.
        // They remain schema-validated whenever a producer supplies them.
        self::required($space, ['slug', 'title'], 'space', $errors);
        if (array_key_exists('id', $space)
            && self::string_field($space['id'], 'space.id', $errors, 1, 128)
            && preg_match('/^[A-Za-z0-9_-]+$/', $space['id']) !== 1) {
            self::error($errors, 'space.id', 'must contain only letters, numbers, underscores, or hyphens');
        }
        if (array_key_exists('slug', $space)
            && self::string_field($space['slug'], 'space.slug', $errors, 2, 64)
            && preg_match('/^[a-z0-9-]{2,64}$/', $space['slug']) !== 1) {
            self::error($errors, 'space.slug', 'must match ^[a-z0-9-]{2,64}$');
        }
        if (array_key_exists('title', $space)) {
            self::string_field($space['title'], 'space.title', $errors, 1, 200);
        }
        if (array_key_exists('status', $space)
            && (! is_string($space['status']) || ! in_array($space['status'], ['draft', 'published', 'suspended'], true))) {
            self::error($errors, 'space.status', 'must be draft, published, or suspended');
        }
    }

    /** @param string[] $errors */
    private static function validate_seo(mixed $seo, array &$errors): void
    {
        if (! is_array($seo) || ! self::is_object_array($seo)) {
            self::error($errors, 'seo', 'must be an object');
            return;
        }
        self::reject_unknown($seo, ['title', 'description', 'canonical'], 'seo', $errors);
        if (array_key_exists('title', $seo)) {
            self::string_field($seo['title'], 'seo.title', $errors, 1, 160);
        }
        if (array_key_exists('description', $seo)) {
            self::string_field($seo['description'], 'seo.description', $errors, 0, 320);
        }
        if (array_key_exists('canonical', $seo)) {
            $path = 'seo.canonical';
            if (self::string_field($seo['canonical'], $path, $errors, 1) && ! self::is_https_url($seo['canonical'])) {
                self::error($errors, $path, 'must be a valid HTTPS URI');
            } elseif (is_string($seo['canonical']) && function_exists('home_url')
                && ! self::same_origin_image($seo['canonical'])) {
                self::error($errors, $path, 'must match home_url() scheme, host, and port');
            }
        }
    }

    /** @param string[] $errors */
    private static function validate_allowed_hosts(mixed $hosts, array &$errors): void
    {
        if (! is_array($hosts) || ! array_is_list($hosts)) {
            self::error($errors, 'allowed_hosts', 'must be an array');
            return;
        }
        $seen = [];
        foreach ($hosts as $index => $host) {
            $path = 'allowed_hosts[' . $index . ']';
            if (! self::string_field($host, $path, $errors, 1, 253)) {
                continue;
            }
            if (preg_match('/^(?=.{1,253}$)(?!.*\.\.)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host) !== 1) {
                self::error($errors, $path, 'must be a lowercase hostname');
            }
            if (isset($seen[$host])) {
                self::error($errors, $path, 'must be unique');
            }
            $seen[$host] = true;
        }
    }

    /** @param string[] $errors */
    private static function validate_blocks(mixed $blocks, array &$errors): void
    {
        if (! is_array($blocks) || ! array_is_list($blocks)) {
            self::error($errors, 'blocks', 'must be an array');
            return;
        }
        foreach ($blocks as $index => $block) {
            $path = 'blocks[' . $index . ']';
            if (! is_array($block) || ! self::is_object_array($block)) {
                self::error($errors, $path, 'must be an object');
                continue;
            }
            if (! array_key_exists('type', $block) || ! is_string($block['type'])) {
                self::error($errors, $path . '.type', 'must be a supported block type');
                continue;
            }
            $type = $block['type'];
            if (! in_array($type, Block::ALLOWED_TYPES, true)) {
                self::error($errors, $path . '.type', 'must be one of ' . implode(', ', Block::ALLOWED_TYPES));
                continue;
            }
            switch ($type) {
                case 'hero':
                    self::reject_unknown($block, ['type', 'heading', 'lede', 'link_id', 'placement_id', 'label'], $path, $errors);
                    self::required($block, ['type', 'heading', 'lede', 'link_id', 'placement_id', 'label'], $path, $errors);
                    self::validate_text_if_present($block, 'heading', $path, $errors, 1, 200);
                    self::validate_text_if_present($block, 'lede', $path, $errors, 0, 500);
                    self::validate_id_if_present($block, 'link_id', $path, $errors);
                    self::validate_placement_if_present($block, 'placement_id', $path, $errors);
                    self::validate_text_if_present($block, 'label', $path, $errors, 1, 160);
                    break;
                case 'text':
                    self::reject_unknown($block, ['type', 'html'], $path, $errors);
                    self::required($block, ['type', 'html'], $path, $errors);
                    self::validate_text_if_present($block, 'html', $path, $errors, 0, 2000);
                    break;
                case 'cards':
                    self::reject_unknown($block, ['type', 'items'], $path, $errors);
                    self::required($block, ['type', 'items'], $path, $errors);
                    self::validate_cards($block, $path, $errors);
                    break;
                case 'cta':
                case 'link':
                    self::reject_unknown($block, ['type', 'label', 'link_id', 'placement_id'], $path, $errors);
                    self::required($block, ['type', 'label', 'link_id', 'placement_id'], $path, $errors);
                    self::validate_text_if_present($block, 'label', $path, $errors, 1, 160);
                    self::validate_id_if_present($block, 'link_id', $path, $errors);
                    self::validate_placement_if_present($block, 'placement_id', $path, $errors);
                    break;
                case 'faq':
                    self::reject_unknown($block, ['type', 'items'], $path, $errors);
                    self::required($block, ['type', 'items'], $path, $errors);
                    self::validate_faq($block, $path, $errors);
                    break;
                case 'comparison':
                case 'table':
                    self::reject_unknown($block, ['type', 'columns', 'rows'], $path, $errors);
                    self::required($block, ['type', 'columns', 'rows'], $path, $errors);
                    self::validate_matrix($block, $path, $errors);
                    break;
                case 'image':
                    self::reject_unknown($block, ['type', 'url', 'alt'], $path, $errors);
                    self::required($block, ['type', 'url', 'alt'], $path, $errors);
                    if (array_key_exists('url', $block)) {
                        if (self::string_field($block['url'], $path . '.url', $errors, 1)
                            && ! self::is_https_url($block['url'])) {
                            self::error($errors, $path . '.url', 'must be a valid HTTPS URI');
                        } elseif (is_string($block['url']) && ! self::same_origin_image($block['url'])) {
                            self::error($errors, $path . '.url', 'must be hosted on this site');
                        }
                    }
                    self::validate_text_if_present($block, 'alt', $path, $errors, 0, 200);
                    break;
            }
        }
    }

    /** @param string[] $errors */
    private static function validate_cards(array $block, string $path, array &$errors): void
    {
        if (! array_key_exists('items', $block)) {
            return;
        }
        if (! is_array($block['items']) || ! array_is_list($block['items'])) {
            self::error($errors, $path . '.items', 'must be an array');
            return;
        }
        foreach ($block['items'] as $index => $item) {
            $item_path = $path . '.items[' . $index . ']';
            if (! is_array($item) || ! self::is_object_array($item)) {
                self::error($errors, $item_path, 'must be an object');
                continue;
            }
            self::reject_unknown($item, ['title', 'body', 'link_id', 'placement_id', 'label'], $item_path, $errors);
            self::required($item, ['title', 'body', 'link_id', 'placement_id', 'label'], $item_path, $errors);
            self::validate_text_if_present($item, 'title', $item_path, $errors, 1, 200);
            self::validate_text_if_present($item, 'body', $item_path, $errors, 0, 1000);
            self::validate_id_if_present($item, 'link_id', $item_path, $errors);
            self::validate_placement_if_present($item, 'placement_id', $item_path, $errors);
            self::validate_text_if_present($item, 'label', $item_path, $errors, 1, 160);
        }
    }

    /** @param string[] $errors */
    private static function validate_faq(array $block, string $path, array &$errors): void
    {
        if (! array_key_exists('items', $block)) {
            return;
        }
        if (! is_array($block['items']) || ! array_is_list($block['items'])) {
            self::error($errors, $path . '.items', 'must be an array');
            return;
        }
        foreach ($block['items'] as $index => $item) {
            $item_path = $path . '.items[' . $index . ']';
            if (! is_array($item) || ! self::is_object_array($item)) {
                self::error($errors, $item_path, 'must be an object');
                continue;
            }
            self::reject_unknown($item, ['q', 'a'], $item_path, $errors);
            self::required($item, ['q', 'a'], $item_path, $errors);
            self::validate_text_if_present($item, 'q', $item_path, $errors, 1, 300);
            self::validate_text_if_present($item, 'a', $item_path, $errors, 1, 2000);
        }
    }

    /** @param string[] $errors */
    private static function validate_matrix(array $block, string $path, array &$errors): void
    {
        if (array_key_exists('columns', $block)) {
            if (! is_array($block['columns']) || ! array_is_list($block['columns'])) {
                self::error($errors, $path . '.columns', 'must be an array');
            } else {
                foreach ($block['columns'] as $index => $column) {
                    self::validate_text_value($column, $path . '.columns[' . $index . ']', $errors, 0, 120);
                }
            }
        }
        if (array_key_exists('rows', $block)) {
            if (! is_array($block['rows']) || ! array_is_list($block['rows'])) {
                self::error($errors, $path . '.rows', 'must be an array');
            } else {
                foreach ($block['rows'] as $row_index => $row) {
                    $row_path = $path . '.rows[' . $row_index . ']';
                    if (! is_array($row) || ! array_is_list($row)) {
                        self::error($errors, $row_path, 'must be an array');
                        continue;
                    }
                    foreach ($row as $column_index => $value) {
                        self::validate_text_value($value, $row_path . '[' . $column_index . ']', $errors, 0, 500);
                    }
                }
            }
        }
    }

    /** @param string[] $errors */
    private static function validate_links(mixed $links, mixed $allowed_hosts, array &$errors): void
    {
        if (! is_array($links) || ! self::is_object_array($links)) {
            self::error($errors, 'links', 'must be an object');
            return;
        }
        $hosts = is_array($allowed_hosts) ? $allowed_hosts : [];
        foreach ($links as $link_id => $link) {
            $path = 'links.' . (string) $link_id;
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $link_id) !== 1) {
                self::error($errors, $path, 'link id must match ^[A-Za-z0-9_-]{1,64}$');
            }
            if (! is_array($link) || ! self::is_object_array($link)) {
                self::error($errors, $path, 'must be an object');
                continue;
            }
            self::reject_unknown($link, ['destination', 'label', 'disclosure', 'status', 'placements'], $path, $errors);
            self::required($link, ['destination', 'label', 'disclosure', 'status', 'placements'], $path, $errors);
            if (array_key_exists('destination', $link)) {
                if (self::string_field($link['destination'], $path . '.destination', $errors, 1)
                    && Validation::https_url($link['destination'], $hosts) === null) {
                    self::error($errors, $path . '.destination', 'must be an allowed HTTPS destination');
                }
            }
            self::validate_text_if_present($link, 'label', $path, $errors, 1, 160);
            self::validate_text_if_present($link, 'disclosure', $path, $errors, 1, 300);
            if (array_key_exists('status', $link)
                && (! is_string($link['status']) || ! in_array($link['status'], ['active', 'inactive'], true))) {
                self::error($errors, $path . '.status', 'must be active or inactive');
            }
            if (array_key_exists('placements', $link)) {
                if (! is_array($link['placements']) || ! array_is_list($link['placements'])) {
                    self::error($errors, $path . '.placements', 'must be an array');
                } else {
                    $seen = [];
                    foreach ($link['placements'] as $index => $placement) {
                        $placement_path = $path . '.placements[' . $index . ']';
                        if (! self::string_field($placement, $placement_path, $errors, 1, 64)
                            || preg_match('/^[a-z0-9-]{1,64}$/', $placement) !== 1) {
                            self::error($errors, $placement_path, 'must match ^[a-z0-9-]{1,64}$');
                        }
                        if (is_string($placement) && isset($seen[$placement])) {
                            self::error($errors, $placement_path, 'must be unique');
                        }
                        if (is_string($placement)) {
                            $seen[$placement] = true;
                        }
                    }
                }
            }
        }
    }

    /** @param string[] $errors */
    private static function validate_agent(mixed $agent, array &$errors): void
    {
        if (! is_array($agent) || ! self::is_object_array($agent)) {
            self::error($errors, 'agent', 'must be an object');
            return;
        }
        self::reject_unknown($agent, ['summary', 'instructions', 'entities'], 'agent', $errors);
        if (array_key_exists('summary', $agent)) {
            self::validate_text_if_present($agent, 'summary', 'agent', $errors, 0, 2000);
        }
        if (array_key_exists('instructions', $agent)) {
            if (! is_array($agent['instructions']) || ! array_is_list($agent['instructions'])) {
                self::error($errors, 'agent.instructions', 'must be an array');
            } else {
                foreach ($agent['instructions'] as $index => $instruction) {
                    self::validate_text_value($instruction, 'agent.instructions[' . $index . ']', $errors, 0, 1000);
                }
            }
        }
        if (array_key_exists('entities', $agent)) {
            if (! is_array($agent['entities']) || ! array_is_list($agent['entities'])) {
                self::error($errors, 'agent.entities', 'must be an array');
            } else {
                foreach ($agent['entities'] as $index => $entity) {
                    $path = 'agent.entities[' . $index . ']';
                    if (! is_array($entity) || ! self::is_object_array($entity)) {
                        self::error($errors, $path, 'must be an object');
                        continue;
                    }
                    self::reject_unknown($entity, ['name', 'type'], $path, $errors);
                    self::required($entity, ['name', 'type'], $path, $errors);
                    self::validate_text_if_present($entity, 'name', $path, $errors, 1, 200);
                    self::validate_text_if_present($entity, 'type', $path, $errors, 1, 120);
                }
            }
        }
    }

    /** @param string[] $errors */
    private static function validate_text_if_present(array $value, string $field, string $path, array &$errors, int $min, int $max): void
    {
        if (array_key_exists($field, $value)) {
            self::validate_text_value($value[$field], $path . '.' . $field, $errors, $min, $max);
        }
    }

    /** @param string[] $errors */
    private static function validate_text_value(mixed $value, string $path, array &$errors, int $min, int $max): void
    {
        self::string_field($value, $path, $errors, $min, $max);
    }

    /** @param string[] $errors */
    private static function validate_id_if_present(array $value, string $field, string $path, array &$errors): void
    {
        if (! array_key_exists($field, $value)) {
            return;
        }
        if (self::string_field($value[$field], $path . '.' . $field, $errors, 1, 64)
            && preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $value[$field]) !== 1) {
            self::error($errors, $path . '.' . $field, 'must match ^[A-Za-z0-9_-]{1,64}$');
        }
    }

    /** @param string[] $errors */
    private static function validate_placement_if_present(array $value, string $field, string $path, array &$errors): void
    {
        if (! array_key_exists($field, $value)) {
            return;
        }
        if (self::string_field($value[$field], $path . '.' . $field, $errors, 1, 64)
            && preg_match('/^[a-z0-9-]{1,64}$/', (string) $value[$field]) !== 1) {
            self::error($errors, $path . '.' . $field, 'must match ^[a-z0-9-]{1,64}$');
        }
    }

    private static function is_object_array(array $value): bool
    {
        return ! array_is_list($value) || $value === [];
    }

    private static function is_https_url(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }
        if (preg_match('/[\r\n\t\0]/', $value) === 1) {
            return false;
        }

        $parts = self::url_parts($value);
        if (! is_array($parts)) {
            return false;
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return trim((string) ($parts['host'] ?? '')) !== '';
    }

    private static function now(): int
    {
        return function_exists('current_time') ? (int) current_time('timestamp', true) : time();
    }
}
