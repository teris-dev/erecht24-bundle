<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Functional;

use Teris\ERecht24Bundle\ERecht24Bundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Teris\ERecht24Bundle\Tests\Fixtures\ResponseQueue;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/** Single-mode application as an end user would set it up. */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new ERecht24Bundle()];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/erecht24-bundle-test/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getCacheDir();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'router' => ['default_uri' => 'https://example.org', 'utf8' => true],
            'http_client' => ['mock_response_factory' => 'test.http_mock'],
            'cache' => ['app' => 'cache.adapter.array'],
        ]);
        $container->extension('erecht24', [
            'api_key' => 'customer-api-key',
            'client_id' => '7',
            'client_secret' => 'the-secret',
        ]);
        $container->services()->set('test.http_mock', ResponseQueue::class)->public();
        $container->services()->set('logger', \Psr\Log\NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@ERecht24Bundle/config/routes.php')->prefix('/erecht24');
    }
}
