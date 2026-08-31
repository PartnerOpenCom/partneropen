<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Admin;

use PartnerOpen\Connector\Application\Pairing;
use PartnerOpen\Connector\Application\SpaceRegistry;
use PartnerOpen\Connector\Domain\Consent;
use PartnerOpen\Connector\Infrastructure\Options;
use PartnerOpen\Connector\Support\Validation;

final class SetupPage
{
    public const MENU_SLUG = 'partneropen-connector';
    public const STATUS_SLUG = 'partneropen-connector-status';
    public const POLICY_VERSION = '1';
    public const TERMS_URL = 'https://partneropen.com/terms';
    public const PRIVACY_URL = 'https://partneropen.com/privacy';
    public const NONCE_ACTION = 'partneropen_connector_setup';
    public const NONCE_FIELD = 'partneropen_connector_setup_nonce';

    private ?string $pairing_code = null;

    /** @var list<string> */
    private array $errors = [];

    private ?string $notice = null;

    /** @var array<string,mixed> */
    private array $form_values = [];

    public function register(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_init', [$this, 'handle_submission']);
    }

    public function add_page(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            self::translate('PartnerOpen'),
            self::translate('PartnerOpen'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page'],
            'dashicons-networking'
        );
    }

    public function render_page(): void
    {
        if (!self::has_capability()) {
            return;
        }

        $connection = Options::connection();
        $status = (string) ($connection['status'] ?? 'local');
        if ($status === 'disconnected') {
            $this->render_setup_form($connection, true);

            return;
        }
        if ($status !== 'local') {
            $this->render_existing_connection($connection);

            return;
        }

        $this->render_setup_form($connection, false);
    }

    /**
     * Normalize and validate the setup form without touching WordPress state.
     *
     * @param array<string,mixed> $input
     * @return array{prefix:?string,partner_email:?string,scopes:list<string>,terms_ack:bool,terms:bool,valid:bool,errors:list<string>}
     */
    public static function sanitize_submission(array $input): array
    {
        $prefix = Validation::prefix(Validation::text($input['prefix'] ?? ''));
        $partner_email = Validation::email(Validation::text($input['partner_email'] ?? ''));
        $scopes = self::normalize_scopes($input['scopes'] ?? ($input['consent'] ?? ($input['consent_scopes'] ?? [])));
        $terms_ack = self::truthy($input['terms_ack'] ?? ($input['terms'] ?? ($input['terms_privacy'] ?? false)));
        $errors = [];

        if ($prefix === null) {
            $errors[] = self::translate('Enter a valid URL prefix using 2–32 lowercase letters, numbers, or hyphens.');
        }

        if ($partner_email === null) {
            $errors[] = self::translate('Enter a valid partner email address.');
        }

        foreach (Consent::scope_meta() as $scope => $meta) {
            if (!empty($meta['required']) && !in_array($scope, $scopes, true)) {
                $label = self::text((string) ($meta['label'] ?? $scope));
                $errors[] = sprintf(
                    self::translate('Grant the required “%s” consent scope.'),
                    $label
                );
            }
        }

        if (!$terms_ack) {
            $errors[] = self::translate('Acknowledge the PartnerOpen Terms and Privacy Notice to continue.');
        }

        return [
            'prefix' => $prefix,
            'partner_email' => $partner_email,
            'scopes' => $scopes,
            'terms_ack' => $terms_ack,
            'terms' => $terms_ack,
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    public static function authorize_submission(bool $has_capability, bool $valid_nonce): bool
    {
        return $has_capability && $valid_nonce;
    }

    public function handle_submission(): void
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
        if (! self::authorize_submission(self::has_capability(), $valid_nonce)) {
            $this->errors[] = self::translate('This setup request could not be verified.');

            return;
        }
        if (!isset($_POST) || !is_array($_POST)) {
            return;
        }

        /** @var array<string,mixed> $raw */
        $raw = self::unslash_array($_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by self::authorize_submission() before this read.
        $action = self::text($raw['partneropen_connector_action'] ?? '');
        if ($action !== 'setup') {
            return;
        }

        $input = $raw;
        $result = self::sanitize_submission($input);
        $this->form_values = $input;
        if (!$result['valid']) {
            $this->errors = $result['errors'];

            return;
        }
        $connection = Options::connection();
        $connection_status = (string) ($connection['status'] ?? 'local');
        if (!in_array($connection_status, ['local', 'disconnected'], true)) {
            $this->errors[] = self::translate('PartnerOpen setup has already been completed.');

            return;
        }

        $existing_prefix = self::text($connection['prefix'] ?? '');
        if (self::has_published_history() && $existing_prefix !== '' && $existing_prefix !== (string) $result['prefix']) {
            $this->errors[] = self::translate('The URL prefix is immutable after the first publish.');

            return;
        }
        $cloud_base = self::text($connection['cloud_base'] ?? '');
        if ($cloud_base === '') {
            $cloud_base = 'https://partneropen.com';
        }

        Options::save_connection([
            'status' => 'local',
            'prefix' => (string) $result['prefix'],
            'partner_email' => (string) $result['partner_email'],
            'cloud_base' => $cloud_base,
            'policy_version' => self::POLICY_VERSION,
        ]);
        Consent::grant($result['scopes'], self::POLICY_VERSION);
        $this->pairing_code = Pairing::issue_code();
        $this->notice = self::translate('Setup is saved. Share this one-time pairing code with your PartnerOpen operator.');
        $this->form_values = [];
    }

    /**
     * @param array<string,mixed> $connection
     */
    private function render_existing_connection(array $connection): void
    {
        $status = self::text((string) ($connection['status'] ?? 'disconnected'));
        printf(
            '<div class="wrap partneropen-admin"><h1>%s</h1><div class="notice notice-info"><p>%s <a href="%s">%s</a> %s</p></div></div>',
            esc_html(self::translate('PartnerOpen')),
            esc_html(sprintf(self::translate('PartnerOpen is %s. Open'), $status)),
            esc_url(self::admin_url('admin.php?page=' . self::STATUS_SLUG)),
            esc_html(self::translate('Status')),
            esc_html(self::translate('to view the owner controls.'))
        );
    }

    /**
     * @param array<string,mixed> $connection
     */
    private function render_setup_form(array $connection, bool $reconnect = false): void
    {
        $prefix = self::text((string) ($this->form_values['prefix'] ?? ($connection['prefix'] ?? '')));
        $email = self::text((string) ($this->form_values['partner_email'] ?? ($connection['partner_email'] ?? '')));
        $selected_scopes = self::normalize_scopes($this->form_values['scopes'] ?? []);
        $terms_ack = self::truthy($this->form_values['terms_ack'] ?? false);
        $meta = Consent::scope_meta();
        $prefix_locked = self::has_published_history();
        $heading = $reconnect ? 'Reconnect PartnerOpen' : 'PartnerOpen';
        $lede = $reconnect
            ? 'Review consent again and issue a fresh pairing code for this disconnected site.'
            : 'Connect one delegated PartnerOpen Space to this site while keeping the durable settings and publication controls here.';

        printf(
            '<div class="wrap partneropen-admin partneropen-admin--setup"><h1>%s</h1><p class="partneropen-admin__lede">%s</p>',
            esc_html(self::translate($heading)),
            esc_html(self::translate($lede))
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
            '<div class="partneropen-admin__notice"><strong>%s</strong><p>%s</p><p>%s</p></div>',
            esc_html(self::translate('Owner controls')),
            esc_html(self::translate('After setup, the partner operates the delegated Space through PartnerOpen Cloud and publishes without an owner content step. This screen does not manage content, SEO, links, or the partner. You keep the Global Pause and Withdraw consent & disconnect controls.')),
            esc_html(self::translate('PartnerOpen never collects cookies, IP addresses, user agents, device fingerprints, unique visitor identifiers, or visitor-level click events.'))
        );

        if ($this->pairing_code !== null) {
            $this->render_pairing_code($this->pairing_code);
        }

        printf('<form method="post" class="partneropen-admin__form">');
        self::nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        printf('<input type="hidden" name="partneropen_connector_action" value="setup"><div class="partneropen-admin__section"><h2>%s</h2><p>%s</p><p><label for="partneropen-prefix"><strong>%s</strong></label><br><input id="partneropen-prefix" class="regular-text" type="text" name="prefix" value="%s" pattern="[a-z0-9-]{2,32}" required autocomplete="off"%s><span class="description">%s</span></p><p><label for="partneropen-partner-email"><strong>%s</strong></label><br><input id="partneropen-partner-email" class="regular-text" type="email" name="partner_email" value="%s" required autocomplete="email"><span class="description">%s</span></p></div>',
            esc_html(self::translate('One-time setup')),
            esc_html(self::translate('Choose the public URL prefix and the email used for the delegated Space invitation.')),
            esc_html(self::translate('URL prefix')),
            esc_attr($prefix),
            esc_attr($prefix_locked ? ' readonly' : ''),
            esc_html(self::translate('This becomes immutable after the first publish. Use 2–32 lowercase letters, numbers, or hyphens.')),
            esc_html(self::translate('Partner email')),
            esc_attr($email),
            esc_html(self::translate('Used only for the invited partner contact recorded for this connection.'))
        );

        printf(
            '<div class="partneropen-admin__section"><h2>%s</h2><p>%s</p><p>%s</p><div class="partneropen-consent-table-wrap"><table class="widefat striped partneropen-consent-table"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>',
            esc_html(self::translate('Consent scopes')),
            esc_html(self::translate('Grant each scope explicitly. Required scopes are fixed for this connection; optional scopes can be left unchecked. The Partner invitation email scope is optional at the option level and is needed only to send an invitation.')),
            esc_html(self::translate('Withholding or withdrawing Affiliate service links makes published links render as plain text and the resolver return 404. Withdrawing Aggregate click counters stops click totals from being recorded.')),
            esc_html(self::translate('Scope')),
            esc_html(self::translate('Purpose')),
            esc_html(self::translate('Data fields')),
            esc_html(self::translate('Recipient')),
            esc_html(self::translate('Retention')),
            esc_html(self::translate('Consent'))
        );

        foreach ($meta as $scope => $details) {
            $required = !empty($details['required']);
            $checked = $required || in_array($scope, $selected_scopes, true);
            $label = self::translate(self::text((string) ($details['label'] ?? $scope)));
            $scope_name = (string) $scope;
            $grant_label = sprintf(self::translate('Grant %s scope'), $label);
            printf(
                '<tr><th scope="row"><strong>%s</strong><br><code>%s</code></th><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><span class="partneropen-consent-table__badge %s">%s</span><br>',
                esc_html($label),
                esc_html(self::translate($scope_name)),
                esc_html(self::translate(self::text((string) ($details['purpose'] ?? '')))),
                esc_html(self::translate(self::display_list($details['fields'] ?? []))),
                esc_html(self::translate(self::text((string) ($details['recipient'] ?? '')))),
                esc_html(self::translate(self::text((string) ($details['retention'] ?? '')))),
                esc_attr($required ? 'partneropen-consent-table__badge--required' : 'partneropen-consent-table__badge--optional'),
                esc_html(self::translate($required ? 'Required' : 'Optional'))
            );
            if ($required) {
                printf(
                    '<input type="hidden" name="scopes[]" value="%s">',
                    esc_attr($scope_name)
                );
            }
            printf(
                '<label><input type="checkbox" name="scopes[]" value="%s"%s%s> <span class="screen-reader-text">%s</span></label></td></tr>',
                esc_attr($scope_name),
                esc_attr($checked ? ' checked' : ''),
                esc_attr($required ? ' disabled' : ''),
                esc_html($grant_label)
            );
        }

        printf('</tbody></table></div></div><div class="partneropen-admin__section partneropen-admin__section--ack"><p><label><input type="checkbox" name="terms_ack" value="1"%s required> %s <a href="%s" target="_blank" rel="noopener">%s</a> %s <a href="%s" target="_blank" rel="noopener">%s</a>.</label></p></div><p><button type="submit" class="button button-primary button-large">%s</button></p></form></div>',
            esc_attr($terms_ack ? ' checked' : ''),
            esc_html(self::translate('I acknowledge the')),
            esc_url(self::TERMS_URL),
            esc_html(self::translate('PartnerOpen Terms of Use')),
            esc_html(self::translate('and')),
            esc_url(self::PRIVACY_URL),
            esc_html(self::translate('Privacy Notice')),
            esc_html(self::translate('Save setup and issue pairing code'))
        );
    }

    private function render_pairing_code(string $code): void
    {
        printf(
            '<div class="partneropen-pairing-code" role="status"><h2>%s</h2><p>%s</p><p><label for="partneropen-pairing-code"><span class="screen-reader-text">%s</span></label><input id="partneropen-pairing-code" class="partneropen-pairing-code__value" type="text" value="%s" readonly onclick="this.select();"><button type="button" class="button" onclick="document.getElementById(\'partneropen-pairing-code\').select();document.execCommand(\'copy\');">%s</button></p><p class="description">%s</p></div>',
            esc_html(self::translate('Pairing code')),
            esc_html(self::translate('Copy this code and give it to the partner operator. It expires in 15 minutes and is shown here only after setup is saved.')),
            esc_html(self::translate('One-time pairing code')),
            esc_attr($code),
            esc_html(self::translate('Copy code')),
            esc_html(self::translate('The plugin makes no outbound request while you complete setup. Pairing starts only when the operator uses this code.'))
        );
    }

    /**
     * @param mixed $value
     */
    private static function display_list(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $parts[] = self::translate((string) $item);
                }
            }

            return implode(', ', $parts);
        }

        return is_scalar($value) ? self::translate((string) $value) : '';
    }

    /**
     * @param mixed $value
     */
    private static function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function normalize_scopes(mixed $value): array
    {
        $meta = Consent::scope_meta();
        $scopes = [];
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($item) && isset($meta[$item])) {
                    $scopes[] = $item;
                } elseif (is_string($key) && isset($meta[$key]) && self::truthy($item)) {
                    $scopes[] = $key;
                }
            }
        } elseif (is_string($value) && isset($meta[$value])) {
            $scopes[] = $value;
        }

        return array_values(array_unique($scopes));
    }

    private static function has_published_history(): bool
    {
        foreach (SpaceRegistry::all() as $space) {
            if ((int) ($space['snapshot_version'] ?? 0) > 0 || (int) ($space['published_at'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
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

    private static function nonce_field(string $action, string $name): void
    {
        if (function_exists('wp_nonce_field')) {
            wp_nonce_field($action, $name);

            return;
        }

        printf(
            '<input type="hidden" name="%s" value="">',
            esc_attr($name)
        );
    }

    private static function admin_url(string $path): string
    {
        return function_exists('admin_url') ? (string) admin_url($path) : $path;
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
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- guarded helper translates the fixed PartnerOpen admin UI literal set (headings, notices, consent labels, and control copy).
        return function_exists('__') ? (string) __($value, 'partneropen-connector') : $value;
    }

}
