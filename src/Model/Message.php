<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Model;

/**
 * Notice from eRecht24 meant for the site owner's admin interface. If
 * `$callToAction` is set, show a button with that text linking to `$link`.
 */
final readonly class Message
{
    public function __construct(
        public string $message,
        public string $messageDe,
        public ?string $callToAction = null,
        public ?string $callToActionDe = null,
        public ?string $link = null,
    ) {
    }
}
