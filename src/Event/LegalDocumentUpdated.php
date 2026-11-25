<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Event;

use Teris\ERecht24Bundle\Model\LegalDocument;

/** Dispatched after a legal text has been pulled and handed to the document handler. */
final readonly class LegalDocumentUpdated
{
    public function __construct(
        public string $connectionId,
        public LegalDocument $document,
    ) {
    }
}
