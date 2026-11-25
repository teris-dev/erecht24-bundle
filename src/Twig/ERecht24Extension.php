<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Twig;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Teris\ERecht24Bundle\Api\ApiException;
use Teris\ERecht24Bundle\Connection\ConfigConnectionProvider;
use Teris\ERecht24Bundle\Document\CacheLegalDocumentStore;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Model\LegalDocument;
use Teris\ERecht24Bundle\Synchronizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * {{ erecht24_document('imprint', app.request.locale) }}
 * {{ erecht24_document('privacyPolicy', 'en', site.erecht24ConnectionId) }}
 *
 * Only registered with the built-in CacheLegalDocumentStore. A missing text is
 * pulled once (read-through); if eRecht24 is unreachable an empty string is
 * returned and an error is logged, so the page itself still renders.
 */
final class ERecht24Extension extends AbstractExtension
{
    public function __construct(
        private readonly CacheLegalDocumentStore $store,
        private readonly Synchronizer $synchronizer,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('erecht24_document', $this->html(...), ['is_safe' => ['html']]),
            new TwigFunction('erecht24_legal_document', $this->document(...)),
        ];
    }

    public function html(string $type, string $locale = 'de', string $connectionId = ConfigConnectionProvider::DEFAULT_ID): string
    {
        return $this->document($type, $connectionId)?->html($locale) ?? '';
    }

    /** The full LegalDocument, e.g. for `modifiedAt`. */
    public function document(string $type, string $connectionId = ConfigConnectionProvider::DEFAULT_ID): ?LegalDocument
    {
        $documentType = DocumentType::from($type);

        try {
            return $this->store->get($connectionId, $documentType) ?? $this->synchronizer->sync($connectionId, $documentType);
        } catch (ApiException $e) {
            $this->logger->error('eRecht24 document "{type}" unavailable: {error}', ['type' => $type, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
