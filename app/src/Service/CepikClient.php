<?php

namespace App\Service;

use App\Exception\CepikClientException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CepikClient
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient, string $baseUri, float $timeout)
    {
        $this->httpClient = $httpClient->withOptions([
            'base_uri' => rtrim($baseUri, '/'),
            'headers' => [
                'Accept' => 'application/json',
            ],
            'timeout' => $timeout,
            // CEPiK endpoint uses legacy DH params; lower OpenSSL security level to keep handshake alive.
            'ciphers' => 'DEFAULT@SECLEVEL=1',
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
            $response = $this->httpClient->request($method, $uri, $options);

            return $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new CepikClientException(
                sprintf('CEPiK API request failed: %s', $exception->getMessage()),
                0,
                $exception,
            );
        }
    }
}
