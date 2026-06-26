<?php

namespace App\Dto;

use InvalidArgumentException;
use JsonException;

class ReportPayloadResponse
{
    /**
     * @param string[] $clarifyingQuestions
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        private readonly bool $needsClarification,
        private readonly array $clarifyingQuestions,
        private readonly ?array $payload,
        private readonly ?string $rawContent = null,
    ) {
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Assistant response is not valid JSON.', 0, $exception);
        }

        if (!\is_array($data)) {
            throw new InvalidArgumentException('Assistant response must be a JSON object.');
        }

        if (!array_key_exists('needs_clarification', $data)) {
            throw new InvalidArgumentException('Response missing "needs_clarification" flag.');
        }

        $needsClarification = (bool) $data['needs_clarification'];
        $clarifyingQuestions = [];

        if (isset($data['clarifying_questions'])) {
            if (!\is_array($data['clarifying_questions'])) {
                throw new InvalidArgumentException('"clarifying_questions" must be an array.');
            }

            foreach ($data['clarifying_questions'] as $question) {
                $clarifyingQuestions[] = (string) $question;
            }
        }

        $payload = null;

        if (isset($data['report_payload'])) {
            if (!\is_array($data['report_payload']) && $data['report_payload'] !== null) {
                throw new InvalidArgumentException('"report_payload" must be an object or null.');
            }

            $payload = $data['report_payload'];
        }

        return new self($needsClarification, $clarifyingQuestions, $payload, $json);
    }

    public function needsClarification(): bool
    {
        return $this->needsClarification;
    }

    /**
     * @return string[]
     */
    public function getClarifyingQuestions(): array
    {
        return $this->clarifyingQuestions;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getReportPayload(): ?array
    {
        return $this->payload;
    }

    public function getRawContent(): ?string
    {
        return $this->rawContent;
    }
}
