<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Model;

/**
 * One eRecht24 project as seen by the application.
 *
 * `$id` is chosen by the application ("default" in single mode, e.g. a site ULID
 * in multi-tenant mode) and becomes part of the push URL.
 * `$clientId` and `$secret` exist once a push client has been registered.
 */
final readonly class Connection
{
    public function __construct(
        public string $id,
        #[\SensitiveParameter] public string $apiKey,
        public ?int $clientId = null,
        #[\SensitiveParameter] public ?string $secret = null,
    ) {
    }
}
