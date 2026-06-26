<?php

namespace App\Service\Ai;

use LogicException;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAiChatClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUri,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     *
     * @return array<string, mixed>
     */
    public function createJsonCompletion(array $messages): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            // część modeli (np. gpt-4o-mini) nie pozwala na ustawienie temperatury != domyślnej
            'response_format' => ['type' => 'json_object'],
        ];

        return $this->request($payload);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        if ($this->apiKey === '') {
            throw new LogicException('OPENAI_API_KEY is not configured.');
        }

        $endpoint = rtrim($this->baseUri, '/') . '/chat/completions';

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $this->apiKey),
                ],
                'json' => $payload,
            ]);

//            var_dump($payload);
//            var_dump($response->toArray(false));die();

            $data = $response->toArray(false);

        } catch (ExceptionInterface $exception) {
            throw new RuntimeException(sprintf('OpenAI request failed: %s', $exception->getMessage()), 0, $exception);
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            throw new RuntimeException('OpenAI response does not contain completion content.');
        }

        return $data;
    }
}
