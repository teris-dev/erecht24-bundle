<?php

declare(strict_types=1);

namespace Teris\ERecht24Bundle\Api;

use Teris\ERecht24Bundle\Model\DocumentType;
use Teris\ERecht24Bundle\Model\LegalDocument;
use Teris\ERecht24Bundle\Model\Message;
use Teris\ERecht24Bundle\Model\Registration;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin client for the eRecht24 Rechtstexte-API v2.
 *
 * Every call takes the customer's API key explicitly, so one instance serves
 * all tenants. The plugin (developer) key identifies this bundle.
 *
 * @see https://api-docs.e-recht24.de/
 */
final class ApiClient
{
    public const DEFAULT_BASE_URI = 'https://api.e-recht24.de/v2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $pluginKey,
        private readonly string $baseUri = self::DEFAULT_BASE_URI,
    ) {
    }

    public function fetchDocument(#[\SensitiveParameter] string $apiKey, DocumentType $type): LegalDocument
    {
        $data = $this->request('GET', '/'.$type->value, $apiKey) ?? [];

        return new LegalDocument(
            type: $type,
            htmlDe: self::string($data, 'html_de'),
            htmlEn: self::string($data, 'html_en'),
            modifiedAt: self::date(self::string($data, 'modified')),
            fetchedAt: new \DateTimeImmutable(),
        );
    }

    /** Returns null if eRecht24 has no message (HTTP 204). */
    public function fetchMessage(#[\SensitiveParameter] string $apiKey): ?Message
    {
        $data = $this->request('GET', '/message', $apiKey);

        if (null === $data) {
            return null;
        }

        return new Message(
            message: self::string($data, 'message'),
            messageDe: self::string($data, 'message_de'),
            callToAction: self::string($data, 'call2action') ?: null,
            callToActionDe: self::string($data, 'call2action_de') ?: null,
            link: self::string($data, 'link') ?: null,
        );
    }

    /**
     * @return list<array<string, mixed>> registered push clients, raw API format
     */
    public function listClients(#[\SensitiveParameter] string $apiKey): array
    {
        $clients = [];
        foreach ($this->request('GET', '/clients', $apiKey) ?? [] as $client) {
            if (\is_array($client)) {
                $clients[] = array_filter($client, \is_string(...), \ARRAY_FILTER_USE_KEY);
            }
        }

        return $clients;
    }

    /**
     * @param array<string, string> $clientInfo cms, cms_version, plugin_name, author_mail
     */
    public function registerClient(#[\SensitiveParameter] string $apiKey, string $pushUri, array $clientInfo): Registration
    {
        $data = $this->request('POST', '/clients', $apiKey, self::clientPayload($pushUri, $clientInfo)) ?? [];

        return self::registration(self::int($data, 'client_id'), $data);
    }

    /**
     * Updates a client. eRecht24 issues a new secret on every update.
     *
     * @param array<string, string> $clientInfo
     */
    public function updateClient(#[\SensitiveParameter] string $apiKey, int $clientId, string $pushUri, array $clientInfo): Registration
    {
        $data = $this->request('PUT', '/clients/'.$clientId, $apiKey, self::clientPayload($pushUri, $clientInfo)) ?? [];

        return self::registration($clientId, $data);
    }

    public function deleteClient(#[\SensitiveParameter] string $apiKey, int $clientId): void
    {
        $this->request('DELETE', '/clients/'.$clientId, $apiKey);
    }

    /**
     * Asks eRecht24 to send a push to the client – to verify the push endpoint.
     *
     * @param 'ping'|'message'|'imprint'|'privacyPolicy'|'privacyPolicySocialMedia' $type
     */
    public function testPush(#[\SensitiveParameter] string $apiKey, int $clientId, string $type = 'ping'): void
    {
        $this->request('POST', '/clients/'.$clientId.'/testPush?'.http_build_query(['type' => $type]), $apiKey);
    }

    /**
     * @param array<string, mixed>|null $json
     *
     * @return array<array-key, mixed>|null decoded body, null for "204 No Content"
     */
    private function request(string $method, string $path, string $apiKey, ?array $json = null): ?array
    {
        $options = ['headers' => [
            'Accept' => 'application/json',
            'eRecht24-api-key' => $apiKey,
            'eRecht24-plugin-key' => $this->pluginKey,
        ]];
        if (null !== $json) {
            $options['json'] = $json;
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUri.$path, $options);
            $status = $response->getStatusCode();
            if (204 === $status) {
                return null;
            }
            $data = '' === $response->getContent(false) ? [] : $response->toArray(false);
        } catch (HttpClientException $e) {
            throw new ApiException('eRecht24 API request failed: '.$e->getMessage(), 0, 'Anfrage an die eRecht24 API fehlgeschlagen: '.$e->getMessage(), previous: $e);
        }

        if ($status >= 300) {
            throw new ApiException(
                message: self::string($data, 'message') ?: 'eRecht24 API responded with HTTP '.$status,
                statusCode: $status,
                messageDe: self::string($data, 'message_de') ?: 'Die eRecht24 API antwortete mit HTTP '.$status,
                errorCode: self::int($data, 'error_code'),
                faqLink: self::string($data, 'faq_link') ?: null,
            );
        }

        return $data;
    }

    /**
     * @param array<string, string> $clientInfo
     *
     * @return array<string, string>
     */
    private static function clientPayload(string $pushUri, array $clientInfo): array
    {
        return ['push_method' => 'POST', 'push_uri' => $pushUri] + $clientInfo;
    }

    /** @param array<array-key, mixed> $data */
    private static function registration(?int $clientId, array $data): Registration
    {
        $secret = self::string($data, 'secret');
        if (null === $clientId || '' === $secret) {
            throw new ApiException('Unexpected eRecht24 API response: client_id or secret missing.', 0, 'Unerwartete Antwort der eRecht24 API: client_id oder secret fehlt.');
        }

        return new Registration($clientId, $secret);
    }

    /** @param array<array-key, mixed> $data */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) || \is_int($value) ? (string) $value : '';
    }

    /** @param array<array-key, mixed> $data */
    private static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) || (\is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }

    private static function date(string $value): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        // eRecht24 sends local German time without zone information.
        return \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('Europe/Berlin')) ?: null;
    }
}
