<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Fixtures;

use Symfony\Component\HttpClient\Response\MockResponse;

/** Response factory for framework.http_client.mock_response_factory. */
final class ResponseQueue
{
    /** @var list<MockResponse> */
    public array $responses = [];
    public int $requests = 0;

    public function __invoke(string $method, string $url): MockResponse
    {
        ++$this->requests;

        return array_shift($this->responses) ?? throw new \LogicException(\sprintf('Unexpected request %s %s', $method, $url));
    }
}
