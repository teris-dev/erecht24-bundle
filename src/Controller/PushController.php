<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Controller;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Teris\ERecht24Bundle\Document\DocumentManagedLocallyException;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Synchronizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Push endpoint called by eRecht24 with `erecht24_secret` and `erecht24_type`
 * (JSON or form body for POST clients, query string for GET clients).
 * Status codes follow the eRecht24 API specification.
 */
final class PushController
{
    public function __construct(
        private readonly ConnectionProviderInterface $connections,
        private readonly Synchronizer $synchronizer,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(Request $request, string $connection): JsonResponse
    {
        $data = $request->query->all() + $request->getPayload()->all();
        $secret = $data['erecht24_secret'] ?? null;
        $type = $data['erecht24_type'] ?? null;

        $known = $this->connections->get($connection);
        if (null === $known) {
            return self::reply(404, 'Unknown connection.');
        }
        if (null === $known->secret) {
            return self::reply(404, 'No push client registered for this connection.');
        }
        if (!\is_string($secret) || !hash_equals($known->secret, $secret)) {
            $this->logger->warning('eRecht24 push with invalid secret for connection "{connection}".', ['connection' => $connection]);

            return self::reply(403, 'Invalid secret.');
        }

        if ('ping' === $type) {
            return self::reply(200, 'pong');
        }

        try {
            if ('message' === $type) {
                $this->synchronizer->syncMessage($connection);

                return self::reply(200, 'Message received.');
            }

            $documentType = \is_string($type) ? DocumentType::tryFrom($type) : null;
            if (null === $documentType) {
                return self::reply(400, 'Invalid erecht24_type.');
            }
            if (!$this->synchronizer->isEnabled($documentType)) {
                return self::reply(422, 'Document type is not used by this client.');
            }

            $this->synchronizer->sync($connection, $documentType);
        } catch (DocumentManagedLocallyException $e) {
            return self::reply(422, $e->getMessage() ?: 'Document is managed locally.');
        } catch (\Throwable $e) {
            $this->logger->error('eRecht24 push "{type}" for connection "{connection}" failed: {error}', [
                'type' => $type, 'connection' => $connection, 'error' => $e->getMessage(), 'exception' => $e,
            ]);

            return self::reply(400, 'Pull failed.');
        }

        return self::reply(200, 'Document updated.');
    }

    private static function reply(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
