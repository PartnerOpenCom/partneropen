<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Public;

use PartnerOpen\Connector\Application\SnapshotStore;
use PartnerOpen\Connector\Application\SpaceRegistry;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;

final class Router
{
    private const QUERY_VARS = [
        'space' => 'partneropen_space',
        'asset' => 'partneropen_asset',
        'link' => 'partneropen_link',
        'placement' => 'partneropen_placement',
    ];

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('init', [$this, 'register_rules']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
            add_action('template_redirect', [$this, 'dispatch']);
        } else {
            $this->register_rules();
        }
        if (function_exists('add_filter')) {
            add_filter('query_vars', [$this, 'query_vars']);
            add_filter('redirect_canonical', [$this, 'skip_canonical_redirect'], 10, 1);
        }
    }

    /**
     * WordPress appends a trailing slash to file-like URLs, which would turn every
     * agent-context and resolver request into a 301. PartnerOpen routes are exact.
     */
    public function skip_canonical_redirect(mixed $redirect_url): mixed
    {
        foreach (self::QUERY_VARS as $query_var) {
            if ($this->query_var($query_var) !== '') {
                return false;
            }
        }

        return $redirect_url;
    }

    public function register_rules(): void
    {
        if (! function_exists('add_rewrite_rule')) {
            return;
        }

        $prefix = preg_quote($this->prefix(), '#');

        // Agent-context files must match before the Space rule: both live under the
        // same prefix and WordPress evaluates extra rewrite rules in registration order.
        add_rewrite_rule(
            '^' . $prefix . '/(AGENTS\.md|agents\.md|llms\.txt|ai-context\.json|manifest\.json|sitemap\.xml)/?$',
            'index.php?' . self::QUERY_VARS['asset'] . '=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^' . $prefix . '/([^/.]+)/?$',
            'index.php?' . self::QUERY_VARS['space'] . '=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^partneropen/go/([^/]+)/([^/]+)/?$',
            'index.php?' . self::QUERY_VARS['link'] . '=$matches[1]&' . self::QUERY_VARS['placement'] . '=$matches[2]',
            'top'
        );
    }

    public static function flush(): void
    {
        (new self())->register_rules();
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    }

    /** @param array<int, string> $vars */
    public function query_vars(array $vars): array
    {
        foreach (self::QUERY_VARS as $query_var) {
            if (! in_array($query_var, $vars, true)) {
                $vars[] = $query_var;
            }
        }

        return $vars;
    }

    public function dispatch(): void
    {
        $link_id = $this->query_var('link');
        if ($link_id !== '') {
            $this->require_publication();
            $result = LinkResolver::resolve($link_id, $this->query_var('placement'));
            if ((int) ($result['status'] ?? 404) !== 302 || ! is_string($result['target'] ?? null)) {
                $this->not_found();
            }
            $this->header('Cache-Control: no-store');
            $this->header('Pragma: no-cache');
            $target = (string) $result['target'];
            $destination_host = $this->url_host($target);
            $redirect_filter = null;
            if ($destination_host !== '' && function_exists('add_filter')) {
                $redirect_filter = static function (mixed $hosts) use ($destination_host): array {
                    $hosts = is_array($hosts) ? $hosts : [];
                    $hosts[] = $destination_host;

                    return array_values(array_unique(array_map(static fn (mixed $host): string => (string) $host, $hosts)));
                };
                add_filter('allowed_redirect_hosts', $redirect_filter, 10, 1);
            }
            $redirected = false;
            if (function_exists('wp_safe_redirect')) {
                $redirected = (bool) wp_safe_redirect($target, 302);
            }
            if (! $redirected) {
                $this->header('Location: ' . $target);
            }
            if ($redirect_filter !== null && function_exists('remove_filter')) {
                remove_filter('allowed_redirect_hosts', $redirect_filter, 10);
            }
            exit;
        }

        $asset = $this->query_var('asset');
        if ($asset !== '') {
            if (! Consent::granted('agent_pack')) {
                $this->not_found();
            }
            $snapshots = $this->require_publication();
            $this->dispatch_asset($asset, $snapshots);
        }

        $slug = $this->query_var('space');
        if ($slug === '') {
            return;
        }
        $space = SpaceRegistry::find_by_slug($slug);
        if (! is_array($space)) {
            $this->not_found();
        }
        $snapshot = $this->require_publication($space);
        $this->header('Content-Type: text/html; charset=utf-8');
        $this->enqueue_stylesheet();
        if (function_exists('wp_print_styles')) {
            wp_print_styles('partneropen-connector-public');
        }
        $resolver_base = $this->site_url('/partneropen/go');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SnapshotRenderer returns allowlisted HTML with every field escaped; KSES would remove its intentional JSON-LD script.
        echo SnapshotRenderer::render($snapshot, $resolver_base);
        exit;
    }

    private function require_publication(?array $space = null): array
    {
        if (Options::paused()) {
            $this->not_found();
        }

        if ($space !== null) {
            $status = (string) ($space['status'] ?? '');
            $id = trim((string) ($space['id'] ?? ''));
            $snapshot = $id !== '' ? SnapshotStore::get($id) : null;
            if ($status !== 'published' || ! is_array($snapshot)) {
                $this->not_found();
            }
            $snapshot_status = is_array($snapshot['space'] ?? null) ? (string) ($snapshot['space']['status'] ?? '') : '';
            if ($snapshot_status !== '' && $snapshot_status !== 'published') {
                $this->not_found();
            }

            return $snapshot;
        }

        $snapshots = SnapshotStore::published();
        if (! is_array($snapshots) || $snapshots === []) {
            $this->not_found();
        }

        return $snapshots;
    }

    /** @param array<string, array<string, mixed>> $snapshots */
    private function dispatch_asset(string $asset, array $snapshots): void
    {
        if ($asset === 'agents.md') {
            $this->header('Location: ' . $this->site_url('/' . $this->prefix() . '/AGENTS.md'));
            $this->status_header(308);
            exit;
        }

        $site_base = $this->site_url('/');
        switch ($asset) {
            case 'AGENTS.md':
                $documents = [];
                foreach ($snapshots as $snapshot) {
                    if (is_array($snapshot)) {
                        $documents[] = AgentPack::agents_md($snapshot);
                    }
                }
                $this->send_body(implode("\n---\n\n", $documents), 'text/markdown; charset=utf-8');
                break;
            case 'llms.txt':
                $this->send_body(AgentPack::llms_txt($snapshots, $site_base), 'text/plain; charset=utf-8');
                break;
            case 'ai-context.json':
                $contexts = [];
                foreach ($snapshots as $snapshot) {
                    if (is_array($snapshot)) {
                        $contexts[] = AgentPack::ai_context($snapshot);
                    }
                }
                $this->send_body($this->json(['spaces' => $contexts]), 'application/json; charset=utf-8');
                break;
            case 'manifest.json':
                $this->send_body($this->json(AgentPack::manifest($snapshots, $site_base)), 'application/json; charset=utf-8');
                break;
            case 'sitemap.xml':
                $this->send_body(AgentPack::sitemap($snapshots, $site_base), 'application/xml; charset=utf-8');
                break;
            default:
                $this->not_found();
        }
        exit;
    }

    private function send_body(string $body, string $content_type): void
    {
        $this->header('Content-Type: ' . $content_type);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- generated Markdown, llms.txt, JSON, or XML body, encoded at build time.
        echo $body;
    }

    private function not_found(): void
    {
        $this->status_header(404);
        $this->header('Cache-Control: no-store, no-cache, must-revalidate');
        $this->header('Pragma: no-cache');
        $this->header('X-Robots-Tag: noindex');
        exit;
    }

    private function status_header(int $status): void
    {
        if (function_exists('status_header')) {
            status_header($status);
            return;
        }
        if (function_exists('http_response_code')) {
            http_response_code($status);
        }
    }

    private function header(string $value): void
    {
        if (! headers_sent()) {
            header($value);
        }
    }

    private function query_var(string $name): string
    {
        if (! function_exists('get_query_var')) {
            return '';
        }
        $query_var = self::QUERY_VARS[$name] ?? $name;
        $value = get_query_var($query_var, '');
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);

    }
    public function enqueue_public_assets(): void
    {
        if ($this->query_var('space') === '') {
            return;
        }

        $this->enqueue_stylesheet();
    }


    private function enqueue_stylesheet(): void
    {
        if (! function_exists('wp_enqueue_style')) {
            return;
        }

        if (defined('PARTNEROPEN_CONNECTOR_URL')) {
            $url = (string) PARTNEROPEN_CONNECTOR_URL . 'assets/css/partneropen.css';
        } elseif (function_exists('plugins_url')) {
            $url = (string) plugins_url('assets/css/partneropen.css', __DIR__ . '/../../partneropen-connector.php');
        } else {
            $url = 'assets/css/partneropen.css';
        }
        $version = defined('PARTNEROPEN_CONNECTOR_VERSION') ? (string) PARTNEROPEN_CONNECTOR_VERSION : '0.1.0';
        wp_enqueue_style('partneropen-connector-public', $url, [], $version);

    }

    private function url_host(string $url): string
    {
        $parts = wp_parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }

        return strtolower((string) ($parts['host'] ?? ''));
    }

    private function prefix(): string
    {
        $connection = Options::connection();
        $prefix = trim((string) ($connection['prefix'] ?? 'partner'), '/');

        return $prefix !== '' ? $prefix : 'partner';
    }

    private function site_url(string $path): string
    {
        if (function_exists('home_url')) {
            return (string) home_url($path);
        }

        return '/' . ltrim($path, '/');
    }

    /** @param array<string, mixed> $value */
    private function json(array $value): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $encoded = wp_json_encode($value, $flags);

        return is_string($encoded) ? $encoded : '{}';
    }
}
