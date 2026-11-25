<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Document;

use Psr\Cache\CacheItemPoolInterface;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Model\LegalDocument;

/**
 * Default handler: keeps the latest texts in a PSR-6 cache pool without expiry.
 * Used when no own `erecht24.document_handler` is configured; read by the
 * erecht24_document() Twig function.
 */
final class CacheLegalDocumentStore implements LegalDocumentHandlerInterface
{
    public function __construct(private readonly CacheItemPoolInterface $cache)
    {
    }

    public function handle(string $connectionId, LegalDocument $document): void
    {
        $this->cache->save($this->cache->getItem(self::key($connectionId, $document->type))->set($document));
    }

    public function get(string $connectionId, DocumentType $type): ?LegalDocument
    {
        $value = $this->cache->getItem(self::key($connectionId, $type))->get();

        return $value instanceof LegalDocument ? $value : null;
    }

    private static function key(string $connectionId, DocumentType $type): string
    {
        // Connection IDs may contain characters reserved by PSR-6.
        return 'erecht24.'.hash('xxh128', $connectionId).'.'.$type->value;
    }
}
