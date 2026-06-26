<?php

namespace App\Service\Ai;

use App\Dto\ReportPayloadResponse;
use App\Service\Prompt\ReportPromptProvider;
use RuntimeException;

class ReportPayloadPlanner
{
    public function __construct(
        private readonly OpenAiChatClient $client,
        private readonly ReportPromptProvider $promptProvider,
    ) {
    }

    public function generate(string $question): ReportPayloadResponse
    {
        if (trim($question) === '') {
            throw new RuntimeException('Question cannot be empty.');
        }

        $messages = array_merge(
            [$this->promptProvider->getSystemMessage()],
            $this->promptProvider->getFewShotMessages(),
            [
                [
                    'role' => 'user',
                    'content' => $question,
                ],
            ],
        );

        $response = $this->client->createJsonCompletion($messages);
        $content = $response['choices'][0]['message']['content'] ?? null;

        if (!\is_string($content)) {
            throw new RuntimeException('Assistant response content is missing.');
        }

        return ReportPayloadResponse::fromJson($content);
    }
}
