<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Public;

use PartnerOpen\Connector\Infrastructure\Options;
use PartnerOpen\Connector\Support\Validation;

final class AgentPack
{
    private const ALLOWED_BLOCK_TYPES = [
        'hero',
        'text',
        'cards',
        'cta',
        'link',
        'faq',
        'comparison',
        'table',
        'image',
    ];

    private const REDACT_KEYS = [
        'email',
        'partner_id',
        'tenant_id',
        'site_id',
        'secret',
        'token',
        'credential',
        'api_key',
        'network',
        'payout',
        'revshare',
        'cpa',
        'click',
        'metric',
        'consent',
        'chat',
        'cloud_base',
    ];

    public static function agents_md(array $snapshot): string
    {
        $snapshot = self::redact($snapshot);
        $space = self::space($snapshot);
        $seo = self::public_seo($snapshot);
        $agent = self::public_agent($snapshot);
        $title = self::text($space['title'] ?? self::translate('PartnerOpen Space'));
        $summary = self::text($agent['summary'] ?? ($seo['description'] ?? ''));
        $canonical = self::canonical_url($snapshot);
        $instructions = is_array($agent['instructions'] ?? null) ? $agent['instructions'] : [];
        $lines = [
            '# ' . $title,
            '',
            '## ' . self::translate('Summary'),
            $summary !== '' ? $summary : self::translate('A published PartnerOpen delegated Space.'),
            '',
            '## ' . self::translate('Public URLs'),
            '- ' . self::translate('Space') . ': ' . ($canonical !== '' ? $canonical : self::fallback_space_url($space)),
            '- ' . self::translate('Agent context') . ': /' . self::prefix() . '/AGENTS.md',
            '',
            '## ' . self::translate('Allowed block types'),
            ...array_map(static fn (string $type): string => '- `' . $type . '`', self::ALLOWED_BLOCK_TYPES),
            '',
            '## ' . self::translate('Instructions'),
        ];
        foreach ($instructions as $instruction) {
            $lines[] = '- ' . $instruction;
        }
        $lines[] = '';
        $lines[] = '## ' . self::translate('Disclosure');
        $lines[] = self::translate('Outbound links may be sponsored and are always disclosed. Follow the same-origin resolver URL and retain `rel="sponsored nofollow noopener"`.');

        return implode("\n", $lines) . "\n";
    }

    public static function llms_txt(array $snapshots, string $site_base): string
    {
        $base = rtrim(trim($site_base), '/');
        $lines = [
            '# PartnerOpen',
            '',
            self::translate('Public delegated Spaces available on this site. External links are disclosed and pass through the same-origin resolver.'),
            '',
        ];
        $published = self::published_snapshots($snapshots);
        foreach ($published as $snapshot) {
            $snapshot = self::redact($snapshot);
            $space = self::space($snapshot);
            $seo = self::public_seo($snapshot);
            $agent = self::public_agent($snapshot);
            $title = self::text($space['title'] ?? self::translate('PartnerOpen Space'));
            $summary = self::text($agent['summary'] ?? ($seo['description'] ?? ''));
            $url = self::canonical_url($snapshot);
            if ($url === '') {
                $url = self::space_url($snapshot, $base);
            }
            $lines[] = '## ' . $title;
            $lines[] = '- ' . self::translate('URL') . ': ' . $url;
            if ($summary !== '') {
                $lines[] = '- ' . self::translate('Summary') . ': ' . $summary;
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Return only public, allowlisted context fields. This intentionally does
     * not expose the raw snapshot or link destinations.
     *
     * @return array<string, mixed>
     */
    public static function ai_context(array $snapshot): array
    {
        $snapshot = self::redact($snapshot);
        $space = self::space($snapshot);
        $canonical = self::canonical_url($snapshot);
        $seo = self::public_seo($snapshot);
        $agent = self::public_agent($snapshot);

        return self::redact([
            'version' => is_scalar($snapshot['version'] ?? null) ? (int) $snapshot['version'] : 3,
            'space' => [
                'slug' => self::text($space['slug'] ?? ''),
                'title' => self::text($space['title'] ?? self::translate('PartnerOpen Space')),
                'status' => self::text($space['status'] ?? 'published'),
            ],
            'seo' => $seo,
            'agent' => $agent,
            'summary' => self::text($agent['summary'] ?? ($seo['description'] ?? '')),
            'allowed_block_types' => self::ALLOWED_BLOCK_TYPES,
            'public_urls' => [
                'space' => $canonical !== '' ? $canonical : self::fallback_space_url($space),
                'agent_context' => '/' . self::prefix() . '/AGENTS.md',
            ],
            'instructions' => self::instructions($snapshot),
            'disclosure' => self::translate('Outbound links may be sponsored and are always disclosed through a same-origin resolver.'),
            'blocks' => self::public_blocks($snapshot),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function manifest(array $snapshots, string $site_base): array
    {
        $base = rtrim(trim($site_base), '/');
        $spaces = [];
        foreach (self::published_snapshots($snapshots) as $snapshot) {
            $snapshot = self::redact($snapshot);
            $space = self::space($snapshot);
            $slug = self::text($space['slug'] ?? '');
            $title = self::text($space['title'] ?? self::translate('PartnerOpen Space'));
            $space_url = self::canonical_url($snapshot);
            if ($space_url === '') {
                $space_url = self::space_url($snapshot, $base);
            }
            $spaces[] = [
                'slug' => $slug,
                'title' => $title,
                'url' => $space_url,
                'agent_url' => self::join_url(self::prefix_base($base), 'AGENTS.md'),
                'allowed_block_types' => self::ALLOWED_BLOCK_TYPES,
            ];
        }

        return self::redact([
            'name' => self::translate('PartnerOpen public spaces'),
            'version' => '0.1.0',
            'base_url' => $base,
            'files' => [
                'AGENTS.md',
                'llms.txt',
                'ai-context.json',
                'manifest.json',
                'sitemap.xml',
            ],
            'spaces' => $spaces,
            'disclosure' => self::translate('Outbound links may be sponsored and are always disclosed through a same-origin resolver.'),
        ]);
    }

    public static function sitemap(array $snapshots, string $site_base): string
    {
        $base = rtrim(trim($site_base), '/');
        $published = self::published_snapshots($snapshots);
        $urls = [];
        foreach ($published as $snapshot) {
            $snapshot = self::redact($snapshot);
            $url = self::canonical_url($snapshot);
            if ($url === '') {
                $url = self::space_url($snapshot, $base);
            }
            if ($url !== '' && preg_match('~/agents\.md(?:[/#?]|$)~i', $url) !== 1) {
                $urls[] = $url;
            }
        }

        if ($published !== []) {
            $urls[] = self::join_url(self::prefix_base($base), 'AGENTS.md');
        }
        $urls = array_values(array_unique($urls));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>' . esc_html($url) . '</loc></url>';
        }

        return $xml . '</urlset>';
    }

    /**
     * Recursively remove keys containing a deny-listed term. Matching is
     * intentionally case-insensitive and applies at every nesting depth.
     *
     * @return array<string|int, mixed>
     */
    public static function redact(array $data): array
    {
        $redacted = [];
        foreach ($data as $key => $value) {
            $key_string = (string) $key;
            $blocked = false;
            foreach (self::REDACT_KEYS as $needle) {
                if (stripos($key_string, $needle) !== false) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                continue;
            }
            $redacted[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $redacted;
    }

    /** @return array<string, mixed> */
    private static function space(array $snapshot): array
    {
        return is_array($snapshot['space'] ?? null) ? $snapshot['space'] : [];
    }

    /** @return array<int, array<string, mixed>> */
    private static function published_snapshots(array $snapshots): array
    {
        $published = [];
        foreach ($snapshots as $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }
            $space = self::space($snapshot);
            if (self::text($space['status'] ?? '') !== 'published') {
                continue;
            }
            $published[] = $snapshot;
        }

        return $published;
    }

    /**
     * A stored canonical is only usable when it points at this site. Snapshots written
     * by an older version may still carry a foreign origin, so the check happens on
     * read as well as on write: the owner's domain never advertises another host.
     */
    private static function canonical_url(array $snapshot): string
    {
        $seo = is_array($snapshot['seo'] ?? null) ? $snapshot['seo'] : [];
        $canonical = self::text($seo['canonical'] ?? '');
        $home = function_exists('home_url') ? (string) home_url('/') : '';
        if ($canonical === '' || $home === '') {
            return '';
        }

        $candidate = wp_parse_url($canonical);
        $home_parts = wp_parse_url($home);
        if (! is_array($candidate)
            || ! is_array($home_parts)
            || isset($candidate['user'])
            || isset($candidate['pass'])) {
            return '';
        }

        $scheme = strtolower((string) ($candidate['scheme'] ?? ''));
        $home_scheme = strtolower((string) ($home_parts['scheme'] ?? ''));
        if (($scheme !== 'http' && $scheme !== 'https') || $scheme !== $home_scheme) {
            return '';
        }

        $host = strtolower(rtrim((string) ($candidate['host'] ?? ''), '.'));
        $home_host = strtolower(rtrim((string) ($home_parts['host'] ?? ''), '.'));
        if ($host === '' || $home_host === '' || $host !== $home_host) {
            return '';
        }

        $port = isset($candidate['port']) ? (int) $candidate['port'] : null;
        $home_port = isset($home_parts['port']) ? (int) $home_parts['port'] : null;
        if ($port !== $home_port) {
            return '';
        }

        return $canonical;
    }

    private static function space_url(array $snapshot, string $site_base): string
    {
        $space = self::space($snapshot);
        $slug = self::text($space['slug'] ?? '');
        if ($slug === '') {
            return $site_base;
        }

        return self::join_url(self::prefix_base($site_base), rawurlencode($slug) . '/');
    }

    private static function fallback_space_url(array $space): string
    {
        $slug = self::text($space['slug'] ?? '');
        $path = '/' . self::prefix() . ($slug !== '' ? '/' . rawurlencode($slug) : '') . '/';

        // Prefer this site's own absolute URL; a snapshot canonical for another
        // origin is dropped upstream, so the fallback must still be resolvable.
        if (function_exists('home_url')) {
            $home = (string) home_url($path);
            if ($home !== '') {
                return $home;
            }
        }

        return $path;
    }

    private static function prefix(): string
    {
        if (class_exists(Options::class)) {
            $connection = Options::connection();
            $prefix = self::text($connection['prefix'] ?? '');
            if ($prefix !== '') {
                return trim($prefix, '/');
            }
        }

        return 'partner';
    }

    private static function join_url(string $base, string $path): string
    {
        $base = rtrim(trim($base), '/');
        $path = ltrim($path, '/');

        return $base === '' ? '/' . $path : $base . '/' . $path;
    }

    private static function prefix_base(string $base): string
    {
        $base = rtrim(trim($base), '/');
        $prefix = trim(self::prefix(), '/');
        if ($prefix === '') {
            return $base;
        }
        if ($base === $prefix || str_ends_with($base, '/' . $prefix)) {
            return $base;
        }

        return self::join_url($base, $prefix);
    }

    /** @return string[] */
    private static function instructions(array $snapshot): array
    {
        $agent = is_array($snapshot['agent'] ?? null) ? $snapshot['agent'] : [];
        $instructions = is_array($agent['instructions'] ?? null) ? $agent['instructions'] : [];
        $result = [];
        foreach ($instructions as $instruction) {
            $instruction = self::text($instruction);
            if ($instruction !== '') {
                $result[] = $instruction;
            }
        }
        if ($result === []) {
            $result[] = self::translate('Use the published page and public context files as the source of truth for this Space.');
            $result[] = self::translate('Keep sponsored-link disclosures visible when describing outbound links.');
        }

        return $result;
    }

    /** @return array<string, string> */
    private static function public_seo(array $snapshot): array
    {
        $seo = is_array($snapshot['seo'] ?? null) ? $snapshot['seo'] : [];
        $public = self::copy_scalar_fields($seo, ['title', 'description', 'canonical']);
        if (self::canonical_url($snapshot) === '') {
            unset($public['canonical']);
        }

        return self::redact($public);
    }

    /** @return array<string, mixed> */
    private static function public_agent(array $snapshot): array
    {
        $agent = is_array($snapshot['agent'] ?? null) ? $snapshot['agent'] : [];
        $public = self::copy_scalar_fields($agent, ['summary']);
        $public['instructions'] = self::instructions($snapshot);
        $entities = [];
        if (is_array($agent['entities'] ?? null)) {
            foreach ($agent['entities'] as $entity) {
                if (! is_array($entity)) {
                    continue;
                }
                $item = self::copy_scalar_fields($entity, ['name', 'type']);
                if ($item !== []) {
                    $entities[] = $item;
                }
            }
        }
        if ($entities !== []) {
            $public['entities'] = $entities;
        }

        return self::redact($public);
    }

    /** @return array<int, array<string, mixed>> */
    private static function public_blocks(array $snapshot): array
    {
        $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];
        $result = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $type = self::text($block['type'] ?? '');
            if (! in_array($type, self::ALLOWED_BLOCK_TYPES, true)) {
                continue;
            }
            $public = ['type' => $type];
            switch ($type) {
                case 'hero':
                    $public += self::copy_scalar_fields($block, ['heading', 'lede', 'label', 'link_id', 'placement_id']);
                    break;
                case 'text':
                    if (is_scalar($block['html'] ?? null)) {
                        $public['text'] = self::plain(self::text($block['html']));
                    }
                    break;
                case 'cards':
                    if (is_array($block['items'] ?? null)) {
                        $public['items'] = self::public_items($block['items'], ['title', 'body', 'label', 'link_id', 'placement_id']);
                    }
                    break;
                case 'cta':
                case 'link':
                    $public += self::copy_scalar_fields($block, ['label', 'link_id', 'placement_id']);
                    break;
                case 'faq':
                    if (is_array($block['items'] ?? null)) {
                        $public['items'] = self::public_items($block['items'], ['q', 'a']);
                    }
                    break;
                case 'comparison':
                case 'table':
                    if (is_array($block['columns'] ?? null)) {
                        $public['columns'] = self::public_values($block['columns']);
                    }
                    if (is_array($block['rows'] ?? null)) {
                        $public['rows'] = self::public_rows($block['rows']);
                    }
                    break;
                case 'image':
                    $public += self::copy_scalar_fields($block, ['url', 'alt']);
                    break;
            }
            $result[] = self::redact($public);
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $items
     * @param array<int, string> $fields
     * @return array<int, array<string, string>>
     */
    private static function public_items(array $items, array $fields): array
    {
        $result = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $public = self::copy_scalar_fields($item, $fields);
            if ($public !== []) {
                $result[] = $public;
            }
        }

        return $result;
    }

    /** @param array<int, mixed> $values */
    private static function public_values(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (is_scalar($value)) {
                $result[] = self::plain(self::text($value));
            }
        }

        return $result;
    }

    /** @param array<int, mixed> $rows */
    private static function public_rows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $result[] = self::public_values($row);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, string> $fields
     * @return array<string, string>
     */
    private static function copy_scalar_fields(array $source, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $source) || ! is_scalar($source[$field])) {
                continue;
            }
            $value = self::plain(self::text($source[$field]));
            if ($value !== '') {
                $result[$field] = $value;
            }
        }

        return $result;
    }

    private static function text(mixed $value): string
    {
        if (is_array($value) || is_object($value) || $value === null) {
            return '';
        }
        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        return class_exists(Validation::class) ? Validation::text($value) : trim(wp_strip_all_tags($value));
    }

    private static function plain(string $value): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function translate(string $text): string
    {
        if (function_exists('__')) {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- guarded helper receives only fixed internal translation literals.
            return (string) __($text, 'partneropen-connector');
        }

        return $text;
    }

}
