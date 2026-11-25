<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Command;

use Teris\ERecht24Bundle\ClientRegistrar;
use Teris\ERecht24Bundle\Connection\ConfigConnectionProvider;
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('erecht24:client:register', 'Registers (or updates) the push endpoint at eRecht24')]
final class ClientRegisterCommand
{
    public function __construct(
        private readonly ClientRegistrar $registrar,
        private readonly ConnectionProviderInterface $connections,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Connection ID')] string $connection = ConfigConnectionProvider::DEFAULT_ID,
        #[Option('Public push URL (default: generated from the route, needs framework.router.default_uri)')] ?string $pushUri = null,
    ): int {
        $pushUri ??= $this->registrar->pushUri($connection);
        $registration = $this->registrar->register($connection, $pushUri);

        $io->success(\sprintf('Push endpoint %s registered as eRecht24 client %d.', $pushUri, $registration->clientId));

        if ($this->connections instanceof ConfigConnectionProvider) {
            $io->writeln(['Add to .env.local (or use secrets:set):', '']);
            $io->writeln(\sprintf('ERECHT24_CLIENT_ID=%d', $registration->clientId));
            $io->writeln(\sprintf('ERECHT24_CLIENT_SECRET=%s', $registration->secret));
            $io->newLine();
        }

        return Command::SUCCESS;
    }
}
