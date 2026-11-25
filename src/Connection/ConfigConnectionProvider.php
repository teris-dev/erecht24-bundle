<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Connection;

use Teris\ERecht24Bundle\Model\Connection;
use Teris\ERecht24Bundle\Model\Registration;

/**
 * Single mode: exactly one connection ("default") configured via environment
 * variables. A new registration cannot be written back to .env.local – the
 * register command prints the values to add instead.
 */
final class ConfigConnectionProvider implements ConnectionProviderInterface
{
    public const DEFAULT_ID = 'default';

    public function __construct(
        #[\SensitiveParameter] private readonly ?string $apiKey,
        private readonly int|string|null $clientId = null,
        #[\SensitiveParameter] private readonly ?string $secret = null,
    ) {
    }

    public function get(string $connectionId): ?Connection
    {
        if (self::DEFAULT_ID !== $connectionId || null === $this->apiKey || '' === $this->apiKey) {
            return null;
        }

        return new Connection(
            self::DEFAULT_ID,
            $this->apiKey,
            null === $this->clientId || '' === $this->clientId ? null : (int) $this->clientId,
            '' === $this->secret ? null : $this->secret,
        );
    }

    public function storeRegistration(string $connectionId, ?Registration $registration): void
    {
        // Configuration is read-only at runtime, see ClientRegisterCommand.
    }
}
