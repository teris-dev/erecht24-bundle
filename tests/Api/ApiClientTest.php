<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Api;

use PHPUnit\Framework\TestCase;
use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\Api\ApiException;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Tests\Fixtures\ApiResponses;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ApiClientTest extends TestCase
{
    public function testFetchDocumentSendsBothKeysAndMapsResponse(): void
    {
        $response = ApiResponses::imprint();
        $api = new ApiClient(new MockHttpClient($response), 'plugin-key');

        $document = $api->fetchDocument('api-key', DocumentType::Imprint);

        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://api.e-recht24.de/v2/imprint', $response->getRequestUrl());
        $headers = $response->getRequestOptions()['normalized_headers'];
        self::assertSame(['eRecht24-api-key: api-key'], $headers['erecht24-api-key']);
        self::assertSame(['eRecht24-plugin-key: plugin-key'], $headers['erecht24-plugin-key']);
        self::assertSame(DocumentType::Imprint, $document->type);
        self::assertSame('<h1>Impressum</h1>', $document->html('de'));
        self::assertSame('<h1>Site Notice</h1>', $document->html('en'));
        self::assertSame('2019-03-12T12:34:00+01:00', $document->modifiedAt?->format(\DATE_ATOM));
    }

    public function testEnglishFallsBackToGermanWhenEmpty(): void
    {
        $api = new ApiClient(new MockHttpClient(ApiResponses::json(['html_de' => 'DE', 'html_en' => ''])), 'p');

        self::assertSame('DE', $api->fetchDocument('k', DocumentType::PrivacyPolicy)->html('en'));
    }

    public function testFetchMessageReturnsNullOnNoContent(): void
    {
        $api = new ApiClient(new MockHttpClient(new MockResponse('', ['http_code' => 204])), 'p');

        self::assertNull($api->fetchMessage('k'));
    }

    public function testFetchMessage(): void
    {
        $api = new ApiClient(new MockHttpClient(ApiResponses::json([
            'message' => 'Update needed', 'message_de' => 'Aktualisieren', 'call2action' => '', 'link' => 'https://e-recht24.de/',
        ])), 'p');

        $message = $api->fetchMessage('k');

        self::assertSame('Aktualisieren', $message?->messageDe);
        self::assertNull($message?->callToAction);
        self::assertSame('https://e-recht24.de/', $message?->link);
    }

    public function testRegisterClientPostsPushUriAndClientInfo(): void
    {
        $response = ApiResponses::json(['secret' => 's3cr3t', 'client_id' => 4592]);
        $api = new ApiClient(new MockHttpClient($response), 'p');

        $registration = $api->registerClient('k', 'https://example.org/erecht24/push', ['cms' => 'Symfony']);

        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://api.e-recht24.de/v2/clients', $response->getRequestUrl());
        self::assertSame(
            ['push_method' => 'POST', 'push_uri' => 'https://example.org/erecht24/push', 'cms' => 'Symfony'],
            json_decode($response->getRequestOptions()['body'], true),
        );
        self::assertSame(4592, $registration->clientId);
        self::assertSame('s3cr3t', $registration->secret);
    }

    public function testApiErrorsBecomeApiException(): void
    {
        $api = new ApiClient(new MockHttpClient(ApiResponses::json([
            'message' => 'A maximum of 3 clients may be created per project.',
            'message_de' => 'Es dürfen maximal 3 Clients pro Projekt angelegt werden.',
            'error_code' => 1649940161,
        ], 403)), 'p');

        try {
            $api->registerClient('k', 'https://example.org', []);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(403, $e->statusCode);
            self::assertSame('A maximum of 3 clients may be created per project.', $e->getMessage());
            self::assertSame('Es dürfen maximal 3 Clients pro Projekt angelegt werden.', $e->messageDe);
            self::assertSame(1649940161, $e->errorCode);
        }
    }

    public function testTransportErrorsBecomeApiException(): void
    {
        $api = new ApiClient(new MockHttpClient(new MockResponse('', ['error' => 'Connection refused'])), 'p');

        $this->expectException(ApiException::class);
        $api->fetchDocument('k', DocumentType::Imprint);
    }
}
