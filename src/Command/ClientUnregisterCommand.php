<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Command;

use Teris\ERecht24Bundle\ClientRegistrar;
use Teris\ERecht24Bundle\Connection\ConfigConnectionProvider;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('erecht24:client:unregister', 'Deletes the push client at eRecht24')]
final class ClientUnregisterCommand
{
    public function __construct(private readonly ClientRegistrar $registrar)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Connection ID')] string $connection = ConfigConnectionProvider::DEFAULT_ID,
    ): int {
        $this->registrar->unregister($connection);
        $io->success('Push client deleted (if one was registered).');

        return Command::SUCCESS;
    }
}
