<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Fixtures;

use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Teris\ERecht24Bundle\Model\Connection;
use Teris\ERecht24Bundle\Model\Registration;

final class InMemoryConnectionProvider implements ConnectionProviderInterface
{
    /** @var array<string, Connection> */
    public array $connections = [];

    public function __construct(Connection ...$connections)
    {
        foreach ($connections as $connection) {
            $this->connections[$connection->id] = $connection;
        }
    }

    public function get(string $connectionId): ?Connection
    {
        return $this->connections[$connectionId] ?? null;
    }

    public function storeRegistration(string $connectionId, ?Registration $registration): void
    {
        $c = $this->connections[$connectionId];
        $this->connections[$connectionId] = new Connection($c->id, $c->apiKey, $registration?->clientId, $registration?->secret);
    }
}
