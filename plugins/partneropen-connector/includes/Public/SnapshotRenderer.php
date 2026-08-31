<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Public;

use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Support\Validation;

final class SnapshotRenderer
{
    /**
     * Render the published, allowlisted snapshot as semantic HTML.
     *
     * Destinations in a snapshot are deliberately never rendered here. Every
     * public link is represented by a same-origin resolver URL and is checked
     * against the snapshot link metadata first.
     */
    public static function render(array $snapshot, string $resolver_base): string
    {
        $space = is_array($snapshot['space'] ?? null) ? $snapshot['space'] : [];
        $title = self::text($space['title'] ?? self::translate('PartnerOpen Space'));
        $slug = self::text($space['slug'] ?? '');
        $blocks = is_array($snapshot['blocks'] ?? null) ? $snapshot['blocks'] : [];
        $faq_schema = [];
        $card_schema = [];
        $body = '';

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $body .= self::render_block($snapshot, $block, $resolver_base, $faq_schema, $card_schema);
        }

        $json_ld = '';
        if ($faq_schema !== []) {
            $json_ld .= self::json_ld([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faq_schema,
            ]);
        }
        if ($card_schema !== []) {
            $json_ld .= self::json_ld([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => $card_schema,
            ]);
        }

        return sprintf(
            '<main class="partneropen-space" data-space-slug="%s"><header class="partneropen-space__header"><h1 class="partneropen-space__title">%s</h1></header><div class="partneropen-space__content">%s</div>%s</main>',
            esc_attr($slug),
            esc_html($title),
            $body,
            $json_ld
        );
    }

    /**
     * @param array<int, array<string, mixed>> $faq_schema
     * @param array<int, array<string, mixed>> $card_schema
     */
    private static function render_block(
        array $snapshot,
        array $block,
        string $resolver_base,
        array &$faq_schema,
        array &$card_schema
    ): string {
        $type = self::text($block['type'] ?? '');

        return match ($type) {
            'hero' => self::render_hero($snapshot, $block, $resolver_base),
            'text' => self::render_text($block),
            'cards' => self::render_cards($snapshot, $block, $resolver_base, $card_schema),
            'cta' => self::render_cta($snapshot, $block, $resolver_base),
            'link' => self::render_link($snapshot, $block, $resolver_base),
            'faq' => self::render_faq($block, $faq_schema),
            'comparison' => self::render_table($block, 'comparison'),
            'table' => self::render_table($block, 'table'),
            'image' => self::render_image($block),
            default => '',
        };
    }

    private static function render_hero(array $snapshot, array $block, string $resolver_base): string
    {
        $space = is_array($snapshot['space'] ?? null) ? $snapshot['space'] : [];
        $heading = self::text($block['heading'] ?? ($space['title'] ?? ''));
        $lede = self::text($block['lede'] ?? '');
        $output = '<section class="partneropen-space__hero">';
        if ($heading !== '') {
            $output .= '<h2 class="partneropen-space__hero-heading">' . esc_html($heading) . '</h2>';
        }
        if ($lede !== '') {
            $output .= '<p class="partneropen-space__hero-lede">' . esc_html($lede) . '</p>';
        }
        $output .= self::render_link_markup($snapshot, $block, $resolver_base, 'partneropen-space__hero-link');

        return $output . '</section>';
    }

    private static function render_text(array $block): string
    {
        $html = is_scalar($block['html'] ?? null) ? (string) $block['html'] : '';

        return '<section class="partneropen-space__text">' . self::rich($html) . '</section>';
    }

    /**
     * @param array<int, array<string, mixed>> $card_schema
     */
    private static function render_cards(array $snapshot, array $block, string $resolver_base, array &$card_schema): string
    {
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];
        $output = '<section class="partneropen-space__cards"><div class="partneropen-space__cards-grid">';
        $position = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $position++;
            $title = self::text($item['title'] ?? '');
            $body = self::text($item['body'] ?? '');
            $output .= '<article class="partneropen-space__card">';
            if ($title !== '') {
                $output .= '<h3 class="partneropen-space__card-title">' . esc_html($title) . '</h3>';
            }
            if ($body !== '') {
                $output .= '<p class="partneropen-space__card-body">' . esc_html($body) . '</p>';
            }
            $output .= self::render_link_markup($snapshot, $item, $resolver_base, 'partneropen-space__card-link');
            $output .= '</article>';

            if ($title !== '') {
                $card_schema[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'name' => self::plain($title),
                    'description' => self::plain($body),
                ];
            }
        }

        return $output . '</div></section>';
    }

    private static function render_cta(array $snapshot, array $block, string $resolver_base): string
    {
        $output = '<section class="partneropen-space__cta">';
        $label = self::text($block['label'] ?? '');
        if ($label !== '') {
            $output .= '<h2 class="partneropen-space__cta-label">' . esc_html($label) . '</h2>';
        }
        $output .= self::render_link_markup($snapshot, $block, $resolver_base, 'partneropen-space__cta-link');

        return $output . '</section>';
    }

    private static function render_link(array $snapshot, array $block, string $resolver_base): string
    {
        $markup = self::render_link_markup($snapshot, $block, $resolver_base, 'partneropen-space__link');

        return $markup === '' ? '' : '<p class="partneropen-space__link-block">' . $markup . '</p>';
    }

    /**
     * @param array<int, array<string, mixed>> $faq_schema
     */
    private static function render_faq(array $block, array &$faq_schema): string
    {
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];
        $output = '<section class="partneropen-space__faq"><div class="partneropen-space__faq-list">';

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = self::text($item['q'] ?? '');
            $answer = self::text($item['a'] ?? '');
            if ($question === '') {
                continue;
            }
            $output .= '<details class="partneropen-space__faq-item"><summary>' . esc_html($question) . '</summary><div class="partneropen-space__faq-answer">' . esc_html($answer) . '</div></details>';
            $faq_schema[] = [
                '@type' => 'Question',
                'name' => self::plain($question),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => self::plain($answer),
                ],
            ];
        }

        return $output . '</div></section>';
    }

    private static function render_table(array $block, string $type): string
    {
        $columns = is_array($block['columns'] ?? null) ? $block['columns'] : [];
        $rows = is_array($block['rows'] ?? null) ? $block['rows'] : [];
        $class = $type === 'comparison' ? 'partneropen-space__comparison' : 'partneropen-space__table';
        $output = '<section class="' . esc_attr($class) . '"><div class="partneropen-space__table-scroll"><table><thead><tr>';

        foreach ($columns as $column) {
            $output .= '<th scope="col">' . esc_html(self::text($column)) . '</th>';
        }

        $output .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $output .= '<tr>';
            foreach ($row as $cell) {
                $output .= '<td>' . esc_html(self::text($cell)) . '</td>';
            }
            $output .= '</tr>';
        }

        return $output . '</tbody></table></div></section>';
    }

    private static function render_image(array $block): string
    {
        $url = self::same_origin_image_url(self::text($block['url'] ?? ''));
        if ($url === '') {
            return '';
        }
        $alt = esc_attr(self::text($block['alt'] ?? ''));

        return '<figure class="partneropen-space__image"><img src="' . $url . '" alt="' . $alt . '"></figure>';
    }

    private static function render_link_markup(array $snapshot, array $block, string $resolver_base, string $class): string
    {
        $link = self::link_data(
            $snapshot,
            self::text($block['link_id'] ?? ''),
            self::text($block['placement_id'] ?? ''),
            $resolver_base
        );
        if ($link === null) {
            return '';
        }

        $label = self::text($block['label'] ?? '');
        if ($label === '') {
            $label = $link['label'];
        }
        if ($label === '') {
            $label = self::translate('Open link');
        }

        $label_markup = '<span class="' . esc_attr($class) . '">' . esc_html($label) . '</span>';
        if (! self::affiliate_links_enabled()) {
            return $label_markup;
        }
        $disclosure = $link['disclosure'] . ' ' . self::translate('Goes to') . ' ' . $link['destination_host'];

        return '<a class="' . esc_attr($class) . '" href="' . $link['href'] . '" rel="sponsored nofollow noopener">' . esc_html($label) . '</a><p class="partneropen-space__disclosure">' . esc_html($disclosure) . '</p>';
    }

    /**
     * @return array{href:string,label:string,disclosure:string,destination_host:string}|null
     */
    private static function link_data(array $snapshot, string $link_id, string $placement_id, string $resolver_base): ?array
    {
        if ($link_id === '' || $placement_id === '') {
            return null;
        }
        $links = is_array($snapshot['links'] ?? null) ? $snapshot['links'] : [];
        $link = is_array($links[$link_id] ?? null) ? $links[$link_id] : null;
        if ($link === null || self::text($link['status'] ?? '') !== 'active') {
            return null;
        }
        $placements = is_array($link['placements'] ?? null) ? $link['placements'] : [];
        $allowed = false;
        foreach ($placements as $placement) {
            if (self::text($placement) === $placement_id) {
                $allowed = true;
                break;
            }
        }
        if (! $allowed) {
            return null;
        }

        $base = rtrim(trim($resolver_base), '/');
        if ($base === '') {
            return null;
        }
        $destination = self::text($link['destination'] ?? '');
        $destination_parts = wp_parse_url($destination);
        if (! is_array($destination_parts)
            || isset($destination_parts['user'])
            || isset($destination_parts['pass'])) {
            return null;
        }
        $destination_host = strtolower(rtrim((string) ($destination_parts['host'] ?? ''), '.'));
        if ($destination_host === '') {
            return null;
        }
        $disclosure = self::text($link['disclosure'] ?? '');
        if ($disclosure === '') {
            $disclosure = self::translate('Sponsored link.');
        }
        $href = esc_url($base . '/' . rawurlencode($link_id) . '/' . rawurlencode($placement_id));
        if ($href === '') {
            return null;
        }

        return [
            'href' => $href,
            'label' => self::text($link['label'] ?? ''),
            'disclosure' => $disclosure,
            'destination_host' => $destination_host,
        ];
    }

    /** @param array<string, mixed> $data */
    private static function json_ld(array $data): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $encoded = wp_json_encode($data, $flags);
        if (! is_string($encoded) || $encoded === '') {
            return '';
        }

        return '<script type="application/ld+json">' . $encoded . '</script>';
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

    private static function rich(string $value): string
    {
        if (class_exists(Validation::class)) {
            return Validation::rich_text($value);
        }
        return wp_kses_post($value);
    }

    private static function affiliate_links_enabled(): bool
    {
        if (defined('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD') && PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD === true) {
            return false;
        }

        return class_exists(Consent::class) && Consent::granted('affiliate_service');
    }

    private static function translate(string $text): string
    {
        if (function_exists('__')) {
            // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- guarded helper receives only fixed internal translation literals.
            return (string) __($text, 'partneropen-connector');
        }

        return $text;
    }

    private static function same_origin_image_url(string $url): string
    {
        if ($url === '') {
            return '';
        }
        $home = function_exists('home_url') ? (string) home_url('/') : '';
        if ($home === '') {
            return '';
        }
        $home_parts = wp_parse_url($home);
        $candidate = wp_parse_url($url);
        if (! is_array($home_parts)
            || ! is_array($candidate)
            || isset($candidate['user'])
            || isset($candidate['pass'])) {
            return '';
        }
        $scheme = strtolower((string) ($candidate['scheme'] ?? ''));
        $home_scheme = strtolower((string) ($home_parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($candidate['host'] ?? ''), '.'));
        $home_host = strtolower(rtrim((string) ($home_parts['host'] ?? ''), '.'));
        $port = isset($candidate['port']) ? (int) $candidate['port'] : null;
        $home_port = isset($home_parts['port']) ? (int) $home_parts['port'] : null;
        if ($scheme === '' || $scheme !== $home_scheme || $host === '' || $host !== $home_host || $port !== $home_port) {
            return '';
        }

        return esc_url($url);
    }
}
