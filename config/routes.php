<?php

declare(strict_types=1);

use Teris\ERecht24Bundle\ClientRegistrar;
use Teris\ERecht24Bundle\Controller\PushController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import with a prefix, e.g. config/routes/erecht24.yaml:
 *
 *   erecht24:
 *       resource: '@ERecht24Bundle/config/routes.php'
 *       prefix: /erecht24
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(ClientRegistrar::PUSH_ROUTE, '/push/{connection}')
        ->controller(PushController::class)
        ->methods(['GET', 'POST'])
        ->requirements(['connection' => '[A-Za-z0-9_.-]{1,64}'])
        ->defaults(['connection' => 'default']);
};
