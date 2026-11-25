<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Model;

/**
 * Result of registering (or updating) a push client at eRecht24.
 */
final readonly class Registration
{
    public function __construct(
        public int $clientId,
        #[\SensitiveParameter] public string $secret,
    ) {
    }
}
