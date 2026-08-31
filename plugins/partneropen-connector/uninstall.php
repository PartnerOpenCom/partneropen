<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/autoload.php';
\PartnerOpen\Connector\Infrastructure\Options::delete_all();
