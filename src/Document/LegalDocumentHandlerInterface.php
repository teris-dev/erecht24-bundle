<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Document;

use Teris\ERecht24Bundle\Model\LegalDocument;

/**
 * Receives every legal text pulled from eRecht24 – after a push, a sync command
 * or a read-through. The application decides where the text goes.
 *
 * Exceptions reach eRecht24 as push response, so the customer sees the failure
 * in the eRecht24 Projekt-Manager:
 *  - DocumentManagedLocallyException → HTTP 422 (text is maintained locally)
 *  - any other exception             → HTTP 400
 *
 * Implement MessageHandlerInterface as well to receive eRecht24 notices.
 */
interface LegalDocumentHandlerInterface
{
    public function handle(string $connectionId, LegalDocument $document): void;
}
