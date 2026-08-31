<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Admin;

final class AdminAssets
{
    public const HANDLE = 'partneropen-connector-admin';

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        }
    }

    public function enqueue(string $hook_suffix = ''): void
    {
        if (!self::is_partneropen_screen($hook_suffix) || !function_exists('wp_enqueue_style')) {
            return;
        }

        if (defined('PARTNEROPEN_CONNECTOR_URL')) {
            $url = (string) PARTNEROPEN_CONNECTOR_URL . 'assets/css/admin.css';
        } elseif (function_exists('plugins_url')) {
            $url = (string) plugins_url('assets/css/admin.css', __DIR__ . '/../../partneropen-connector.php');
        } else {
            $url = 'assets/css/admin.css';
        }
        $version = defined('PARTNEROPEN_CONNECTOR_VERSION') ? (string) PARTNEROPEN_CONNECTOR_VERSION : '0.1.0';
        wp_enqueue_style(self::HANDLE, $url, [], $version);
    }

    public static function is_partneropen_screen(string $hook_suffix): bool
    {
        return in_array(
            $hook_suffix,
            [
                'toplevel_page_' . SetupPage::MENU_SLUG,
                SetupPage::MENU_SLUG . '_page_' . SetupPage::STATUS_SLUG,
            ],
            true
        );
    }
}
