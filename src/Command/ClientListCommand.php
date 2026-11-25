<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Command;

use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\Connection\ConfigConnectionProvider;
use Teris\ERecht24Bundle\Connection\ConnectionNotFoundException;
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('erecht24:client:list', 'Lists all push clients of the eRecht24 project (max. 3)')]
final class ClientListCommand
{
    private const COLUMNS = ['client_id', 'push_method', 'push_uri', 'plugin_name', 'updated_at'];

    public function __construct(
        private readonly ApiClient $api,
        private readonly ConnectionProviderInterface $connections,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Connection ID')] string $connection = ConfigConnectionProvider::DEFAULT_ID,
    ): int {
        $current = $this->connections->get($connection) ?? throw new ConnectionNotFoundException($connection);

        $rows = [];
        foreach ($this->api->listClients($current->apiKey) as $client) {
            $row = array_map(static fn (string $column): string => \is_scalar($client[$column] ?? null) ? (string) $client[$column] : '', self::COLUMNS);
            $row[0] = ($client['client_id'] ?? null) === $current->clientId ? $row[0].' *' : $row[0];
            $rows[] = $row;
        }

        $io->table(self::COLUMNS, $rows);
        $io->writeln('* = this connection');

        return Command::SUCCESS;
    }
}
