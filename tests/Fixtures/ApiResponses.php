<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Tests\Fixtures;

use Symfony\Component\HttpClient\Response\MockResponse;

final class ApiResponses
{
    public static function imprint(): MockResponse
    {
        return self::json([
            'html_de' => '<h1>Impressum</h1>',
            'html_en' => '<h1>Site Notice</h1>',
            'created' => '2019-01-01 14:23:00',
            'modified' => '2019-03-12 12:34:00',
            'pushed' => '2019-04-12 14:01:00',
            'warnings' => '',
        ]);
    }

    /** @param array<array-key, mixed> $data */
    public static function json(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
