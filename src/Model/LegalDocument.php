<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Model;

/**
 * A legal text in both languages, as delivered by eRecht24.
 */
final readonly class LegalDocument
{
    public function __construct(
        public DocumentType $type,
        public string $htmlDe,
        public string $htmlEn,
        public ?\DateTimeImmutable $modifiedAt,
        public \DateTimeImmutable $fetchedAt,
    ) {
    }

    /**
     * HTML for the given locale ("de", "de_AT", "en", …). Every locale other than
     * German gets the English text; an empty English text falls back to German.
     */
    public function html(string $locale): string
    {
        if (str_starts_with(strtolower($locale), 'de') || '' === trim($this->htmlEn)) {
            return $this->htmlDe;
        }

        return $this->htmlEn;
    }
}
