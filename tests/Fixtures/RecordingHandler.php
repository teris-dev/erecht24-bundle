<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Fixtures;

use Teris\ERecht24Bundle\Document\LegalDocumentHandlerInterface;
use Teris\ERecht24Bundle\Document\MessageHandlerInterface;
use Teris\ERecht24Bundle\Model\LegalDocument;
use Teris\ERecht24Bundle\Model\Message;

final class RecordingHandler implements LegalDocumentHandlerInterface, MessageHandlerInterface
{
    /** @var list<array{string, LegalDocument}> */
    public array $documents = [];
    /** @var list<array{string, Message}> */
    public array $messages = [];
    public ?\Throwable $failWith = null;

    public function handle(string $connectionId, LegalDocument $document): void
    {
        if (null !== $this->failWith) {
            throw $this->failWith;
        }
        $this->documents[] = [$connectionId, $document];
    }

    public function handleMessage(string $connectionId, Message $message): void
    {
        $this->messages[] = [$connectionId, $message];
    }
}
