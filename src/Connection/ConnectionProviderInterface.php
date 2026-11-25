<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Connection;

use Teris\ERecht24Bundle\Model\Connection;
use Teris\ERecht24Bundle\Model\Registration;

/**
 * Supplies the eRecht24 credentials of a connection.
 *
 * Single mode uses the built-in ConfigConnectionProvider (.env.local).
 * Multi-tenant applications implement this interface on top of their
 * database (e.g. the site or user settings) and set `erecht24.connection_provider`.
 */
interface ConnectionProviderInterface
{
    /** Null if the connection is unknown or has no API key. */
    public function get(string $connectionId): ?Connection;

    /**
     * Persists client ID and secret after registering or updating the push
     * client; null after the client has been deleted.
     */
    public function storeRegistration(string $connectionId, ?Registration $registration): void;
}
