<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Document;

use Teris\ERecht24Bundle\Model\Message;

/**
 * Optional: implement on your LegalDocumentHandlerInterface service to receive
 * notices from eRecht24 directly. Independently of this, every notice is
 * dispatched as MessageReceived event.
 */
interface MessageHandlerInterface
{
    public function handleMessage(string $connectionId, Message $message): void;
}
