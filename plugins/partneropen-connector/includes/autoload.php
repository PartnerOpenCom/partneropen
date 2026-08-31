<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'PartnerOpen\\Connector\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    if ($relative === false || $relative === '') {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});
