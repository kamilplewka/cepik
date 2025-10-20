<?php

namespace App\Service;

use App\Exception\CepikClientException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CepikClient
{
    private HttpClientInterface $httpClient;
    private float $timeout;

    /**
     * @var string[]
     */
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.6533.120 Safari/537.36 Edg/127.0.2651.105',
    ];

    public function __construct(HttpClientInterface $httpClient, string $baseUri, float $timeout)
    {
        // Force at least 60 seconds to allow sluggish API responses.
        $this->timeout = max($timeout, 60.0);

        $this->httpClient = $httpClient->withOptions([
            'base_uri' => rtrim($baseUri, '/'),
            'timeout' => $this->timeout,
            // CEPiK endpoint uses legacy DH params; lower OpenSSL security level to keep handshake alive.
            'ciphers' => 'DEFAULT@SECLEVEL=1',
            'http_version' => '2.0',
        ]);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function listVehicles(array $query): array
    {
        return $this->request('GET', '/pojazdy', [
            'query' => $query,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getVersion(): array
    {
        return $this->request('GET', '/version');
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function getVehicle(string $id, array $query = []): array
    {
        return $this->request('GET', sprintf('/pojazdy/%s', urlencode($id)), [
            'query' => $query,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $requestOptions = $this->enrichOptions($options);

            // Add slight jitter to avoid hammering API in predictable rhythm.
            usleep(random_int(100, 400) * 1000);

            $response = $this->httpClient->request($method, $uri, $requestOptions);

            return $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new CepikClientException(
                sprintf('CEPiK API request failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function enrichOptions(array $options): array
    {
        $headers = $options['headers'] ?? [];

        $headers = array_merge([
            'Accept' => 'application/json, text/plain;q=0.9, */*;q=0.8',
            'Accept-Language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Connection' => 'keep-alive',
            'Referer' => 'https://api.cepik.gov.pl/swagger/',
            'User-Agent' => $this->userAgents[random_int(0, \count($this->userAgents) - 1)],
        ], $headers);

        $options['headers'] = $headers;
        $options['timeout'] = $options['timeout'] ?? $this->timeout;

        return $options;
    }
}
