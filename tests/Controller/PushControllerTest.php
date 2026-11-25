<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Teris\ERecht24Bundle\Api\ApiClient;
use Teris\ERecht24Bundle\Controller\PushController;
use Teris\ERecht24Bundle\Document\DocumentManagedLocallyException;
use Teris\ERecht24Bundle\Model\Connection;
use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Synchronizer;
use Teris\ERecht24Bundle\Tests\Fixtures\ApiResponses;
use Teris\ERecht24Bundle\Tests\Fixtures\InMemoryConnectionProvider;
use Teris\ERecht24Bundle\Tests\Fixtures\RecordingHandler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;

final class PushControllerTest extends TestCase
{
    private RecordingHandler $handler;
    private MockHttpClient $http;
    private PushController $controller;

    protected function setUp(): void
    {
        $this->handler = new RecordingHandler();
        $this->http = new MockHttpClient();
        $connections = new InMemoryConnectionProvider(
            new Connection('site-1', 'api-key', 7, 'secret-1'),
            new Connection('unregistered', 'api-key'),
        );
        $synchronizer = new Synchronizer(new ApiClient($this->http, 'p'), $connections, $this->handler, [DocumentType::Imprint, DocumentType::PrivacyPolicy]);
        $this->controller = new PushController($connections, $synchronizer);
    }

    public function testPingAnswersPong(): void
    {
        $response = ($this->controller)($this->jsonPush('secret-1', 'ping'), 'site-1');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"message":"pong"}', $response->getContent());
    }

    /** @return iterable<string, array{Request}> */
    public static function encodings(): iterable
    {
        $data = ['erecht24_secret' => 'secret-1', 'erecht24_type' => 'imprint'];
        yield 'json body' => [Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($data, \JSON_THROW_ON_ERROR))];
        yield 'form body' => [Request::create('/', 'POST', $data)];
        yield 'query (GET client)' => [Request::create('/', 'GET', $data)];
    }

    #[DataProvider('encodings')]
    public function testDocumentPushPullsAndHandsOverDocument(Request $request): void
    {
        $this->http->setResponseFactory([ApiResponses::imprint()]);

        $response = ($this->controller)($request, 'site-1');

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $this->handler->documents);
        self::assertSame('site-1', $this->handler->documents[0][0]);
        self::assertSame('<h1>Impressum</h1>', $this->handler->documents[0][1]->htmlDe);
    }

    public function testWrongSecretIsRejected(): void
    {
        self::assertSame(403, ($this->controller)($this->jsonPush('wrong', 'imprint'), 'site-1')->getStatusCode());
        self::assertSame(403, ($this->controller)($this->jsonPush(null, 'imprint'), 'site-1')->getStatusCode());
        self::assertSame([], $this->handler->documents);
    }

    public function testUnknownOrUnregisteredConnectionIsNotFound(): void
    {
        self::assertSame(404, ($this->controller)($this->jsonPush('secret-1', 'ping'), 'nope')->getStatusCode());
        self::assertSame(404, ($this->controller)($this->jsonPush('', 'ping'), 'unregistered')->getStatusCode());
    }

    public function testInvalidTypeIsBadRequest(): void
    {
        self::assertSame(400, ($this->controller)($this->jsonPush('secret-1', 'nonsense'), 'site-1')->getStatusCode());
    }

    public function testDisabledTypeIsUnprocessable(): void
    {
        self::assertSame(422, ($this->controller)($this->jsonPush('secret-1', 'privacyPolicySocialMedia'), 'site-1')->getStatusCode());
    }

    public function testHandlerCanDeclineLocallyManagedDocument(): void
    {
        $this->http->setResponseFactory([ApiResponses::imprint()]);
        $this->handler->failWith = new DocumentManagedLocallyException();

        self::assertSame(422, ($this->controller)($this->jsonPush('secret-1', 'imprint'), 'site-1')->getStatusCode());
    }

    public function testFailedPullIsBadRequest(): void
    {
        $this->http->setResponseFactory([ApiResponses::json(['message' => 'Imprint not found.'], 404)]);

        self::assertSame(400, ($this->controller)($this->jsonPush('secret-1', 'imprint'), 'site-1')->getStatusCode());
    }

    public function testMessageIsPassedToHandler(): void
    {
        $this->http->setResponseFactory([ApiResponses::json(['message' => 'Hi', 'message_de' => 'Hallo'])]);

        self::assertSame(200, ($this->controller)($this->jsonPush('secret-1', 'message'), 'site-1')->getStatusCode());
        self::assertSame('Hallo', $this->handler->messages[0][1]->messageDe);
    }

    private function jsonPush(?string $secret, string $type): Request
    {
        return Request::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(
            ['erecht24_secret' => $secret, 'erecht24_type' => $type],
            \JSON_THROW_ON_ERROR,
        ));
    }
}
