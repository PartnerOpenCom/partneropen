<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Admin;

use PartnerOpen\Connector\Application\Pairing;
use PartnerOpen\Connector\Application\SnapshotStore;
use PartnerOpen\Connector\Application\SpaceRegistry;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;

final class StatusPage
{
    public const NONCE_ACTION = 'partneropen_connector_status';
    public const NONCE_FIELD = 'partneropen_connector_status_nonce';
    public const DISCONNECT_CONFIRM_FIELD = 'disconnect_confirm';

    /** @var list<string> */
    private const OPTIONAL_SCOPES = ['agent_pack', 'aggregate_metrics', 'affiliate_service'];

    /** @var list<string> */
    private array $errors = [];

    private ?string $notice = null;

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function add_page(): void
    {
        if (!function_exists('add_submenu_page')) {
            return;
        }

        add_submenu_page(
            SetupPage::MENU_SLUG,
            self::translate('Status'),
            self::translate('Status'),
            'manage_options',
            SetupPage::STATUS_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!self::has_capability()) {
            return;
        }

        $connection = Options::connection();
        if ((string) ($connection['status'] ?? 'local') === 'local') {
            printf(
                '<div class="wrap partneropen-admin"><h1>%s</h1><div class="notice notice-info"><p>%s</p></div></div>',
                esc_html(self::translate('Status')),
                esc_html(self::translate('Complete the one-time PartnerOpen setup before using the status controls.'))
            );

            return;
        }

        $this->render_status($connection);
    }

    /**
     * Validate a disconnect confirmation without changing state.
     *
     * @param array<string,mixed> $input
     * @return array{confirmed:bool,confirm:bool,valid:bool,status:string,preserve_snapshots:bool,errors:list<string>}
     */
    public static function sanitize_disconnect(array $input): array
    {
        $confirmed = self::truthy($input[self::DISCONNECT_CONFIRM_FIELD] ?? ($input['confirm'] ?? ($input['confirmed'] ?? false)));
        $errors = $confirmed ? [] : [self::translate('Confirm withdrawal and disconnect before continuing.')];

        return [
            'confirmed' => $confirmed,
            'confirm' => $confirmed,
            'valid' => $confirmed,
            'status' => $confirmed ? 'disconnected' : 'unchanged',
            'preserve_snapshots' => true,
            'errors' => $errors,
        ];
    }

    public static function authorize_action(bool $has_capability, bool $valid_nonce): bool
    {
        return $has_capability && $valid_nonce;
    }

    public function handle_actions(): void
    {
        $request_method = '';
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $request_value = function_exists('wp_unslash')
                ? (string) wp_unslash((string) $_SERVER['REQUEST_METHOD']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REQUEST_METHOD is unslashed and sanitized immediately below.
                : (string) $_SERVER['REQUEST_METHOD']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- native fallback is used when WordPress is not loaded.
            $request_method = function_exists('sanitize_text_field')
                ? (string) sanitize_text_field($request_value)
                : self::text($request_value);
        }
        if ($request_method !== 'POST') {
            return;
        }
        if (! isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce field must be read to verify it; authorization happens before all other POST fields are read.
        $nonce = function_exists('wp_unslash')
            ? (string) wp_unslash($_POST[self::NONCE_FIELD]) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- this is the nonce value read for verification and sanitized immediately below.
            : (string) $_POST[self::NONCE_FIELD]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- standalone fallback; nonce is verified before any other POST field.
        $nonce = function_exists('sanitize_text_field') ? (string) sanitize_text_field($nonce) : self::text($nonce);
        $valid_nonce = function_exists('wp_verify_nonce')
            ? (bool) wp_verify_nonce($nonce, self::NONCE_ACTION)
            : false;
        if (! self::authorize_action(self::has_capability(), $valid_nonce)) {
            $this->errors[] = self::translate('This status request could not be verified.');

            return;
        }
        if (!isset($_POST) || !is_array($_POST)) {
            return;
        }

        /** @var array<string,mixed> $raw */
        $raw = self::unslash_array($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by self::authorize_action() before this read.
        $action = self::text($raw['partneropen_connector_action'] ?? '');
        if (!in_array($action, ['pause', 'disconnect', 'revoke_scope', 'delete_snapshots'], true)) {
            return;
        }

        if ($action === 'pause') {
            $this->handle_pause($raw);
        } elseif ($action === 'disconnect') {
            $this->handle_disconnect($raw);
        } elseif ($action === 'revoke_scope') {
            $this->handle_revoke_scope($raw);
        } else {
            $this->handle_delete_snapshots($raw);
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    private function handle_pause(array $input): void
    {
        $paused = self::truthy($input['paused'] ?? false);
        Options::set_paused($paused);
        $this->notice = $paused
            ? self::translate('Global Pause is on. PartnerOpen URLs now return 404.')
            : self::translate('Global Pause is off. The last published snapshots are available again.');
    }

    /**
     * @param array<string,mixed> $input
     */
    private function handle_disconnect(array $input): void
    {
        $result = self::sanitize_disconnect($input);
        if (!$result['valid']) {
            $this->errors = $result['errors'];

            return;
        }

        Options::save_connection([
            'status' => 'disconnected',
            'disconnected_at' => time(),
        ]);
        Pairing::revoke_secret();
        Consent::revoke_all();
        $this->notice = self::translate('Consent withdrawn and this site is disconnected. Local published snapshots remain available until you explicitly delete them. Reconnecting requires a new pairing code and new consent.');
    }

    /**
     * @param array<string,mixed> $input
     */
    private function handle_revoke_scope(array $input): void
    {
        $scope = self::text($input['scope'] ?? '');
        if (!in_array($scope, self::OPTIONAL_SCOPES, true)) {
            $this->errors[] = self::translate('That optional scope cannot be changed here.');

            return;
        }

        Consent::revoke($scope);
        $this->notice = match ($scope) {
            'agent_pack' => self::translate('Agent context file consent withdrawn. New context files will no longer be exchanged.'),
            'aggregate_metrics' => self::translate('Aggregate click counter consent withdrawn. Click totals will no longer be recorded.'),
            'affiliate_service' => self::translate('Affiliate service link consent withdrawn. Published links will render as plain text and the resolver will return 404.'),
            default => self::translate('Optional consent withdrawn.'),
        };
    }

    /**
     * @param array<string,mixed> $input
     */
    private function handle_delete_snapshots(array $input): void
    {
        if (!self::truthy($input['delete_snapshots_confirm'] ?? false)) {
            $this->errors[] = self::translate('Confirm deletion of local snapshots before continuing.');

            return;
        }

        $snapshots = SnapshotStore::all();
        foreach (array_keys($snapshots) as $space_id) {
            SnapshotStore::delete((string) $space_id);
        }
        $this->notice = self::translate('Local snapshots deleted. No Cloud-side snapshot copy exists in this milestone.');
    }

    /**
     * @param array<string,mixed> $connection
     */
    private function render_status(array $connection): void
    {
        $status = (string) ($connection['status'] ?? 'disconnected');
        $paused = Options::paused();
        $scopes = [];
        foreach (Consent::scope_meta() as $scope => $meta) {
            if (Consent::granted((string) $scope)) {
                $scopes[] = self::translate(self::text((string) ($meta['label'] ?? $scope)));
            }
        }

        printf(
            '<div class="wrap partneropen-admin partneropen-admin--status"><h1>%s</h1><p class="partneropen-admin__lede">%s</p>',
            esc_html(self::translate('PartnerOpen Status')),
            esc_html(self::translate('Owner-visible connection facts and the two publication and consent controls retained by this site.'))
        );

        if ($this->notice !== null) {
            printf(
                '<div class="notice notice-success"><p>%s</p></div>',
                esc_html($this->notice)
            );
        }
        foreach ($this->errors as $error) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html($error)
            );
        }

        printf(
            '<section class="partneropen-status-grid" aria-label="%s">',
            esc_attr(self::translate('Connection facts'))
        );
        $this->render_fact('Connection status', self::status_label($status));
        $this->render_fact('Paired at', self::format_time((int) ($connection['paired_at'] ?? 0)));
        $this->render_fact('Last sync', self::format_time((int) ($connection['last_sync_at'] ?? 0)));
        $this->render_fact('URL prefix', (string) ($connection['prefix'] ?? ''));
        $this->render_fact('Published spaces', (string) SpaceRegistry::count_published());
        $this->render_fact('Global Pause', $paused ? 'On' : 'Off');
        $this->render_fact('Granted consent', $scopes === [] ? 'None' : implode(', ', $scopes));
        printf('</section>');

        $this->render_pause_card($paused);
        $this->render_optional_scopes();
        $this->render_disconnect_card();
        $this->render_snapshot_delete_card();
        printf('</div>');
    }

    private function render_pause_card(bool $paused): void
    {
        printf(
            '<section class="partneropen-admin__card partneropen-pause-card"><div><h2>%s</h2><p>%s</p></div><form method="post">',
            esc_html(self::translate('Global Pause')),
            esc_html(self::translate('When paused, all PartnerOpen URLs return 404. Partner work and the local snapshot are preserved; no Cloud copy exists in this milestone. Resume restores the last published snapshot.'))
        );
        self::nonce_field();
        printf(
            '<input type="hidden" name="partneropen_connector_action" value="pause"><input type="hidden" name="paused" value="%s"><button type="submit" class="button%s">%s</button></form></section>',
            esc_attr($paused ? '0' : '1'),
            esc_attr($paused ? ' button-primary' : ''),
            esc_html(self::translate($paused ? 'Resume PartnerOpen URLs' : 'Pause all PartnerOpen URLs'))
        );
    }

    private function render_optional_scopes(): void
    {
        $meta = Consent::scope_meta();
        printf(
            '<section class="partneropen-admin__section partneropen-admin__optional-scopes"><h2>%s</h2><p>%s</p><p>%s</p>',
            esc_html(self::translate('Optional consent')),
            esc_html(self::translate('You can withdraw an optional scope without deleting local published snapshots.')),
            esc_html(self::translate('Withholding or withdrawing Affiliate service links makes published links render as plain text and the resolver return 404. Withdrawing Aggregate click counters stops click totals from being recorded.'))
        );
        foreach (self::OPTIONAL_SCOPES as $scope) {
            if (!isset($meta[$scope])) {
                continue;
            }
            $label = self::translate(self::text((string) ($meta[$scope]['label'] ?? $scope)));
            $consequence = match ($scope) {
                'agent_pack' => self::translate('New agent context files will no longer be exchanged.'),
                'aggregate_metrics' => self::translate('Withdrawing this scope stops aggregate click totals from being recorded.'),
                'affiliate_service' => self::translate('Withdrawing this scope makes published links render as plain text and the resolver return 404.'),
                default => self::translate('This optional data exchange will stop.'),
            };
            printf(
                '<div class="partneropen-optional-scope"><div><strong>%s</strong><p>%s</p></div>',
                esc_html($label),
                esc_html($consequence)
            );
            if (Consent::granted($scope)) {
                printf('<form method="post">');
                self::nonce_field();
                printf(
                    '<input type="hidden" name="partneropen_connector_action" value="revoke_scope"><input type="hidden" name="scope" value="%s"><button type="submit" class="button">%s</button></form>',
                    esc_attr($scope),
                    esc_html(self::translate('Withdraw scope'))
                );
            } else {
                printf(
                    '<span class="description">%s</span>',
                    esc_html(self::translate('Not granted'))
                );
            }
            printf('</div>');
        }
        printf('</section>');
    }

    private function render_disconnect_card(): void
    {
        printf(
            '<section class="partneropen-admin__card partneropen-disconnect-card"><h2>%s</h2><p>%s</p><p>%s</p><form method="post">',
            esc_html(self::translate('Withdraw consent & disconnect')),
            esc_html(self::translate("This withdraws consent, stops all data exchange, and revokes this site's key. No Cloud-side copy exists in this milestone; future service data is governed by that service's notice.")),
            esc_html(self::translate('Local published snapshots stay on this site. Reconnecting requires a new pairing code and new consent.'))
        );
        self::nonce_field();
        printf(
            '<input type="hidden" name="partneropen_connector_action" value="disconnect"><p><label><input type="checkbox" name="%s" value="1" required> %s</label></p><button type="submit" class="button">%s</button></form></section>',
            esc_attr(self::DISCONNECT_CONFIRM_FIELD),
            esc_html(self::translate('I understand that consent will be withdrawn and this site will disconnect.')),
            esc_html(self::translate('Withdraw consent & disconnect'))
        );
    }

    private function render_snapshot_delete_card(): void
    {
        printf(
            '<section class="partneropen-admin__section partneropen-snapshot-delete"><h2>%s</h2><p>%s</p><form method="post">',
            esc_html(self::translate('Delete local snapshots')),
            esc_html(self::translate("This separate action removes the snapshots stored in this site's WordPress database. No Cloud-side snapshot copy exists in this milestone."))
        );
        self::nonce_field();
        printf(
            '<input type="hidden" name="partneropen_connector_action" value="delete_snapshots"><p><label><input type="checkbox" name="delete_snapshots_confirm" value="1" required> %s</label></p><button type="submit" class="button">%s</button></form></section>',
            esc_html(self::translate('I understand that local snapshots will be deleted.')),
            esc_html(self::translate('Delete local snapshots'))
        );
    }

    private function render_fact(string $label, string $value): void
    {
        $display = $value === '' ? self::translate('Not set') : self::translate($value);
        printf(
            '<div class="partneropen-status-grid__item"><span>%s</span><strong>%s</strong></div>',
            esc_html(self::translate($label)),
            esc_html($display)
        );
    }

    private static function status_label(string $status): string
    {
        return match ($status) {
            'connected' => self::translate('Connected'),
            'disconnected' => self::translate('Disconnected'),
            default => self::translate('Not paired'),
        };
    }

    private static function format_time(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return self::translate('Not yet');
        }
        if (function_exists('wp_date')) {
            return (string) wp_date('Y-m-d H:i T', $timestamp);
        }

        return gmdate('Y-m-d H:i \U\T\C', $timestamp);
    }

    /**
     * @param mixed $value
     */
    private static function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private static function unslash_array(array $input): array
    {
        if (function_exists('wp_unslash')) {
            /** @var array<string,mixed> $unslashed */
            $unslashed = wp_unslash($input);

            return $unslashed;
        }

        return $input;
    }

    private static function has_capability(): bool
    {
        return function_exists('current_user_can') && (bool) current_user_can('manage_options');
    }

    private static function nonce_field(): void
    {
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

            return;
        }

        printf(
            '<input type="hidden" name="%s" value="">',
            esc_attr(self::NONCE_FIELD)
        );
    }

    private static function text(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(self::strip_all_tags((string) $value));
    }

    private static function strip_all_tags(string $value): string
    {
        if (function_exists('wp_strip_all_tags')) {
            return (string) wp_strip_all_tags($value);
        }

        return strip_tags($value); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- native fallback used when WordPress is not loaded (standalone tests).
    }

    private static function translate(string $value): string
    {
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- guarded helper translates the fixed PartnerOpen status UI literal set (headings, notices, consent labels, facts, and control copy).
        return function_exists('__') ? (string) __($value, 'partneropen-connector') : $value;
    }

}
