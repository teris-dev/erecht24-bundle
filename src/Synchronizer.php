<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle;

use Psr\EventDispatcher\EventDispatcherInterface;
use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\Connection\ConnectionNotFoundException;
use Teris\ERecht24Bundle\Connection\ConnectionProviderInterface;
use Teris\ERecht24Bundle\Document\LegalDocumentHandlerInterface;
use Teris\ERecht24Bundle\Document\MessageHandlerInterface;
use Teris\ERecht24Bundle\Event\LegalDocumentUpdated;
use Teris\ERecht24Bundle\Event\MessageReceived;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Model\LegalDocument;
use Teris\ERecht24Bundle\Model\Message;

/**
 * Pulls texts and notices from eRecht24 and hands them to the application.
 */
final class Synchronizer
{
    /**
     * @param list<DocumentType> $documentTypes the texts this application uses
     */
    public function __construct(
        private readonly ApiClient $api,
        private readonly ConnectionProviderInterface $connections,
        private readonly LegalDocumentHandlerInterface $handler,
        private readonly array $documentTypes,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function isEnabled(DocumentType $type): bool
    {
        return \in_array($type, $this->documentTypes, true);
    }

    /** @return list<DocumentType> */
    public function documentTypes(): array
    {
        return $this->documentTypes;
    }

    public function sync(string $connectionId, DocumentType $type): LegalDocument
    {
        $document = $this->api->fetchDocument($this->connection($connectionId)->apiKey, $type);

        $this->handler->handle($connectionId, $document);
        $this->dispatcher?->dispatch(new LegalDocumentUpdated($connectionId, $document));

        return $document;
    }

    /** @return list<LegalDocument> */
    public function syncAll(string $connectionId): array
    {
        return array_map(fn (DocumentType $type) => $this->sync($connectionId, $type), $this->documentTypes);
    }

    public function syncMessage(string $connectionId): ?Message
    {
        $message = $this->api->fetchMessage($this->connection($connectionId)->apiKey);
        if (null === $message) {
            return null;
        }

        if ($this->handler instanceof MessageHandlerInterface) {
            $this->handler->handleMessage($connectionId, $message);
        }
        $this->dispatcher?->dispatch(new MessageReceived($connectionId, $message));

        return $message;
    }

    private function connection(string $connectionId): Model\Connection
    {
        return $this->connections->get($connectionId) ?? throw new ConnectionNotFoundException($connectionId);
    }
}
