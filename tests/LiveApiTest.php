<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\ERecht24Bundle;
use Teris\ERecht24Bundle\Model\DocumentType;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Against the real API with the documented demo key (read-only).
 * Run with: composer test:live
 */
#[Group('live')]
final class LiveApiTest extends TestCase
{
    private const DEMO_API_KEY = 'e81cbf18a5239377aa4972773d34cc2b81ebc672879581bce29a0a4c414bf117';

    public function testFetchesImprintAndPrivacyPolicyInBothLanguages(): void
    {
        $api = new ApiClient(HttpClient::create(), ERecht24Bundle::PLUGIN_KEY);

        foreach ([DocumentType::Imprint, DocumentType::PrivacyPolicy] as $type) {
            $document = $api->fetchDocument(self::DEMO_API_KEY, $type);
            self::assertStringContainsString('<h1>', $document->htmlDe, $type->value);
            self::assertStringContainsString('<h1>', $document->htmlEn, $type->value);
            self::assertNotNull($document->modifiedAt);
        }
    }

    public function testListsClients(): void
    {
        $clients = (new ApiClient(HttpClient::create(), ERecht24Bundle::PLUGIN_KEY))->listClients(self::DEMO_API_KEY);

        self::assertArrayHasKey('push_uri', $clients[0] ?? []);
    }
}
