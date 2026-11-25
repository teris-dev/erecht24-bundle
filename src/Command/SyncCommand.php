<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Command;

use Teris\ERecht24Bundle\Connection\ConfigConnectionProvider;
use Teris\ERecht24Bundle\Synchronizer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('erecht24:sync', 'Pulls all configured legal texts from eRecht24 (e.g. by cron, as fallback to push)')]
final class SyncCommand
{
    public function __construct(private readonly Synchronizer $synchronizer)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Connection ID')] string $connection = ConfigConnectionProvider::DEFAULT_ID,
    ): int {
        foreach ($this->synchronizer->syncAll($connection) as $document) {
            $io->writeln(\sprintf(
                '<info>✔</info> %s (modified %s)',
                $document->type->value,
                $document->modifiedAt?->format('Y-m-d H:i') ?? 'unknown',
            ));
        }

        return Command::SUCCESS;
    }
}
