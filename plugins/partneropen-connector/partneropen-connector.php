<?php
/**
 * Plugin Name: PartnerOpen Connector
 * Description: neutral delegated-space connector for WordPress
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * Text Domain: partneropen-connector
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('PARTNEROPEN_CONNECTOR_VERSION', '0.1.0');
if (! defined('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD')) {
    define('PARTNEROPEN_CONNECTOR_DIRECTORY_BUILD', false);
}
define('PARTNEROPEN_CONNECTOR_FILE', __FILE__);
define(
    'PARTNEROPEN_CONNECTOR_DIR',
    function_exists('plugin_dir_path') ? plugin_dir_path(__FILE__) : __DIR__ . '/',
);
define(
    'PARTNEROPEN_CONNECTOR_URL',
    function_exists('plugin_dir_url') ? plugin_dir_url(__FILE__) : '',
);

if (is_readable(PARTNEROPEN_CONNECTOR_DIR . 'includes/autoload.php')) {
    require_once PARTNEROPEN_CONNECTOR_DIR . 'includes/autoload.php';
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', static function (): void {
        $class = '\\PartnerOpen\\Connector\\Infrastructure\\Plugin';
        if (class_exists($class)) {
            (new $class())->boot();
        }
    });
}

if (function_exists('add_action')) {
    add_action(
        'partneropen_connector_prune_clicks',
        ['PartnerOpen\\Connector\\Application\\ClickCounter', 'prune_scheduled'],
    );
}

if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, static function (): void {
        $defaults = '\\PartnerOpen\\Connector\\Infrastructure\\Options';
        if (class_exists($defaults)) {
            foreach ($defaults::defaults() as $key => $value) {
                if ($key === 'partneropen_secret') {
                    if (function_exists('add_option')) {
                        add_option($key, '', '', false);
                    }
                    continue;
                }

                if (function_exists('add_option')) {
                    add_option($key, $value, '', true);
                }
            }
        }

        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')
            && ! wp_next_scheduled('partneropen_connector_prune_clicks')) {
            wp_schedule_event(time(), 'daily', 'partneropen_connector_prune_clicks');
        }

        $router = '\\PartnerOpen\\Connector\\Public\\Router';
        if (class_exists($router)) {
            $router::flush();
        }
    });
}

if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(__FILE__, static function (): void {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('partneropen_connector_prune_clicks');
        }
        if (function_exists('flush_rewrite_rules')) {
            flush_rewrite_rules(false);
        }
    });
}
