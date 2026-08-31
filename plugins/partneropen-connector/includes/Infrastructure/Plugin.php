<?php

declare(strict_types=1);

namespace PartnerOpen\Connector\Infrastructure;

use PartnerOpen\Connector\Admin\AdminAssets;
use PartnerOpen\Connector\Admin\SetupPage;
use PartnerOpen\Connector\Admin\StatusPage;
use PartnerOpen\Connector\Application\ConsentGate;
use PartnerOpen\Connector\Http\RestApi;
use PartnerOpen\Connector\Public\Router;

final class Plugin
{
    public function boot(): void
    {
        $registrars = [
            ConsentGate::class,
            RestApi::class,
            Router::class,
            SetupPage::class,
            StatusPage::class,
            AdminAssets::class,
        ];
        foreach ($registrars as $class) {
            if (! class_exists($class)) {
                continue;
            }
            $registrar = new $class();
            if (method_exists($registrar, 'register')) {
                $registrar->register();
            }
        }
    }
}
