<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Connection;

final class ConnectionNotFoundException extends \RuntimeException
{
    public function __construct(public readonly string $connectionId)
    {
        parent::__construct(\sprintf('eRecht24 connection "%s" is unknown or has no API key.', $connectionId));
    }
}
