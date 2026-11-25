<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle;

use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\Connection\ConnectionNotFoundException;
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Teris\ERecht24Bundle\Model\Connection;
use Teris\ERecht24Bundle\Model\Registration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Registers this application's push endpoint at eRecht24.
 *
 * Call register() when a customer enters an API key and unregister() when the
 * key is removed or the site is deleted – eRecht24 allows only 3 clients per project.
 */
final class ClientRegistrar
{
    public const PUSH_ROUTE = 'erecht24_push';

    /**
     * @param array<string, string> $clientInfo cms, cms_version, plugin_name, author_mail
     */
    public function __construct(
        private readonly ApiClient $api,
        private readonly ConnectionProviderInterface $connections,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly array $clientInfo,
    ) {
    }

    /**
     * Registers the push client, or updates it if the connection already has one
     * (eRecht24 then issues a new secret). The result is passed to the provider.
     */
    public function register(string $connectionId, ?string $pushUri = null): Registration
    {
        $connection = $this->connection($connectionId);
        $pushUri ??= $this->pushUri($connectionId);

        $registration = null === $connection->clientId
            ? $this->api->registerClient($connection->apiKey, $pushUri, $this->clientInfo)
            : $this->api->updateClient($connection->apiKey, $connection->clientId, $pushUri, $this->clientInfo);

        $this->connections->storeRegistration($connectionId, $registration);

        return $registration;
    }

    public function unregister(string $connectionId): void
    {
        $connection = $this->connection($connectionId);
        if (null === $connection->clientId) {
            return;
        }

        $this->api->deleteClient($connection->apiKey, $connection->clientId);
        $this->connections->storeRegistration($connectionId, null);
    }

    public function pushUri(string $connectionId): string
    {
        return $this->urlGenerator->generate(self::PUSH_ROUTE, ['connection' => $connectionId], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function connection(string $connectionId): Connection
    {
        return $this->connections->get($connectionId) ?? throw new ConnectionNotFoundException($connectionId);
    }
}
