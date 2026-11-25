<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Command;

use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\Connection\ConfigConnectionProvider;
use Teris\ERecht24Bundle\Connection\ConnectionNotFoundException;
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('erecht24:client:test-push', 'Lets eRecht24 send a test push to this application')]
final class ClientTestPushCommand
{
    private const TYPES = ['ping', 'message', 'imprint', 'privacyPolicy', 'privacyPolicySocialMedia'];

    public function __construct(
        private readonly ApiClient $api,
        private readonly ConnectionProviderInterface $connections,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Connection ID')] string $connection = ConfigConnectionProvider::DEFAULT_ID,
        #[Option('ping|message|imprint|privacyPolicy|privacyPolicySocialMedia', shortcut: 't', suggestedValues: self::TYPES)] string $type = 'ping',
    ): int {
        if (!\in_array($type, self::TYPES, true)) {
            $io->error('Invalid type. Allowed: '.implode(', ', self::TYPES));

            return Command::INVALID;
        }

        $current = $this->connections->get($connection) ?? throw new ConnectionNotFoundException($connection);
        if (null === $current->clientId) {
            $io->error('No push client registered for this connection. Run erecht24:client:register first.');

            return Command::FAILURE;
        }

        $this->api->testPush($current->apiKey, $current->clientId, $type);
        $io->success(\sprintf('eRecht24 delivered the "%s" push successfully.', $type));

        return Command::SUCCESS;
    }
}
