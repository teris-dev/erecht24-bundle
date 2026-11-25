<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle;

use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\Command\ClientListCommand;
use Teris\ERecht24Bundle\Command\ClientRegisterCommand;
use Teris\ERecht24Bundle\Command\ClientTestPushCommand;
use Teris\ERecht24Bundle\Command\ClientUnregisterCommand;
use Teris\ERecht24Bundle\Command\SyncCommand;
use Teris\ERecht24Bundle\Connection\ConfigConnectionProvider;
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Teris\ERecht24Bundle\Controller\PushController;
use Teris\ERecht24Bundle\Document\CacheLegalDocumentStore;
use Teris\ERecht24Bundle\Document\LegalDocumentHandlerInterface;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Twig\ERecht24Extension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ERecht24Bundle extends AbstractBundle
{
    /**
     * Developer key identifying this bundle at eRecht24. Plugin keys are public
     * by design (they ship with every open-source plugin).
     * TODO: replace the documented demo key once eRecht24 has issued ours.
     */
    public const PLUGIN_KEY = '3jh4uhn8u69i97kj9timk466748996ikhkjhlk67plli08lhkijgh8z4363gr53v';

    protected string $extensionAlias = 'erecht24';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('plugin_key')->defaultValue(self::PLUGIN_KEY)->cannotBeEmpty()->end()
                ->scalarNode('base_uri')->defaultValue(ApiClient::DEFAULT_BASE_URI)->cannotBeEmpty()->end()
                ->scalarNode('api_key')->defaultNull()->info('Single mode: API key of the eRecht24 project')->end()
                ->scalarNode('client_id')->defaultNull()->info('Single mode: output of erecht24:client:register')->end()
                ->scalarNode('client_secret')->defaultNull()->info('Single mode: output of erecht24:client:register')->end()
                ->scalarNode('connection_provider')->defaultNull()->info('Multi-tenant: service ID implementing ConnectionProviderInterface')->end()
                ->scalarNode('document_handler')->defaultNull()->info('Service ID implementing LegalDocumentHandlerInterface; null = built-in cache store')->end()
                ->scalarNode('cache_pool')->defaultValue('cache.app')->info('Pool of the built-in store; use a persistent one in production')->end()
                ->arrayNode('documents')
                    ->info('Legal texts this application uses')
                    ->defaultValue([DocumentType::Imprint->value, DocumentType::PrivacyPolicy->value])
                    ->enumPrototype()->values(array_column(DocumentType::cases(), 'value'))->end()
                ->end()
                ->arrayNode('client_info')
                    ->info('Sent to eRecht24 when registering the push client')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cms')->defaultValue('Symfony')->end()
                        ->scalarNode('cms_version')->defaultValue(Kernel::VERSION)->end()
                        ->scalarNode('plugin_name')->defaultValue('teris-dev/erecht24-bundle')->end()
                        ->scalarNode('author_mail')->defaultValue('')->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{
     *     plugin_key: string, base_uri: string, api_key: ?string, client_id: int|string|null, client_secret: ?string,
     *     connection_provider: ?string, document_handler: ?string, cache_pool: string, documents: list<string>,
     *     client_info: array<string, string>,
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $logger = service('logger')->nullOnInvalid();

        $services->set(ApiClient::class)
            ->args([service('http_client'), $config['plugin_key'], $config['base_uri']]);

        if (null === $config['connection_provider']) {
            $services->set(ConfigConnectionProvider::class)
                ->args([$config['api_key'], $config['client_id'], $config['client_secret']]);
            $services->alias(ConnectionProviderInterface::class, ConfigConnectionProvider::class);
        } else {
            $services->alias(ConnectionProviderInterface::class, $config['connection_provider']);
        }

        if (null === $config['document_handler']) {
            $services->set(CacheLegalDocumentStore::class)->args([service($config['cache_pool'])]);
            $services->alias(LegalDocumentHandlerInterface::class, CacheLegalDocumentStore::class);

            if (class_exists(Environment::class)) {
                $services->set(ERecht24Extension::class)
                    ->args([service(CacheLegalDocumentStore::class), service(Synchronizer::class), $logger])
                    ->tag('twig.extension')
                    ->tag('monolog.logger', ['channel' => 'erecht24']);
            }
        } else {
            $services->alias(LegalDocumentHandlerInterface::class, $config['document_handler']);
        }

        $services->set(Synchronizer::class)
            ->args([
                service(ApiClient::class),
                service(ConnectionProviderInterface::class),
                service(LegalDocumentHandlerInterface::class),
                array_map(DocumentType::from(...), $config['documents']),
                service('event_dispatcher')->nullOnInvalid(),
            ]);

        $services->set(ClientRegistrar::class)
            ->args([service(ApiClient::class), service(ConnectionProviderInterface::class), service('router'), $config['client_info']]);

        $services->set(PushController::class)
            ->public()
            ->args([service(ConnectionProviderInterface::class), service(Synchronizer::class), $logger])
            ->tag('controller.service_arguments')
            ->tag('monolog.logger', ['channel' => 'erecht24']);

        $services->set(ClientRegisterCommand::class)
            ->args([service(ClientRegistrar::class), service(ConnectionProviderInterface::class)])->tag('console.command');
        $services->set(ClientUnregisterCommand::class)
            ->args([service(ClientRegistrar::class)])->tag('console.command');
        $services->set(ClientListCommand::class)
            ->args([service(ApiClient::class), service(ConnectionProviderInterface::class)])->tag('console.command');
        $services->set(ClientTestPushCommand::class)
            ->args([service(ApiClient::class), service(ConnectionProviderInterface::class)])->tag('console.command');
        $services->set(SyncCommand::class)
            ->args([service(Synchronizer::class)])->tag('console.command');
    }
}
