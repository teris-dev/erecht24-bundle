<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Event;

use Teris\ERecht24Bundle\Model\Message;

/** Dispatched when eRecht24 pushed a notice for the site owner. */
final readonly class MessageReceived
{
    public function __construct(
        public string $connectionId,
        public Message $message,
    ) {
    }
}
