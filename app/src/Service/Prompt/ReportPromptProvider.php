<?php

namespace App\Service\Prompt;

use DateTimeImmutable;
use JsonException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ReportPromptProvider
{
    private ?string $systemPrompt = null;

    /**
     * @var array<int, array<string, string>>|null
     */
    private ?array $fewShotMessages = null;

    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    /**
     * @return array{role:string, content:string}
     */
    public function getSystemMessage(): array
    {
        return [
            'role' => 'system',
            'content' => $this->systemPrompt ??= $this->buildSystemPrompt(),
        ];
    }

    /**
     * @return array<int, array{role:string, content:string}>
     */
    public function getFewShotMessages(): array
    {
        if ($this->fewShotMessages !== null) {
            return $this->fewShotMessages;
        }

        $path = $this->projectDir . '/prompts/report_payload_examples.json';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Prompt examples file not found: %s', $path));
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException(sprintf('Unable to read prompt examples: %s', $path));
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to decode prompt examples JSON.', 0, $exception);
        }

        if (!\is_array($data)) {
            throw new RuntimeException('Prompt examples JSON must be an array.');
        }

        return $this->fewShotMessages = array_map(static function (array $message): array {
            if (!isset($message['role'], $message['content'])) {
                throw new RuntimeException('Each prompt example must contain role and content.');
            }

            return [
                'role' => (string) $message['role'],
                'content' => (string) $message['content'],
            ];
        }, $data);
    }

    private function buildSystemPrompt(): string
    {
        $path = $this->projectDir . '/prompts/report_payload_system.md';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('System prompt template not found: %s', $path));
        }

        $template = file_get_contents($path);

        if ($template === false) {
            throw new RuntimeException(sprintf('Unable to read system prompt template: %s', $path));
        }

        $today = (new DateTimeImmutable())->format('Y-m-d');

        $replacements = [
            '{{TODAY}}' => $today,
            '{{REGIONS_TABLE}}' => $this->buildRegionsTable(),
            '{{VEHICLE_ATTRIBUTES}}' => $this->buildVehicleAttributesList(),
        ];

        return strtr($template, $replacements);
    }

    private function buildRegionsTable(): string
    {
        $path = $this->projectDir . '/data/wojewodztwa.json';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('Voivodeship dictionary not found: %s', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read voivodeship dictionary: %s', $path));
        }
        try {
            $json = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to decode wojewodztwa.json.', 0, $exception);
        }
        $records = $json['data']['attributes']['dostepne-rekordy-slownika'] ?? null;

        if (!\is_array($records)) {
            throw new RuntimeException('Unexpected wojewodztwa.json structure.');
        }

        $lines = [];

        foreach ($records as $record) {
            if (!isset($record['klucz-slownika'], $record['wartosc-slownika'])) {
                continue;
            }

            $code = (string) $record['klucz-slownika'];
            $label = (string) $record['wartosc-slownika'];
            $lines[] = sprintf('%s – %s', $code, ucfirst(strtolower($label)));
        }

        return implode("\n", $lines);
    }

    private function buildVehicleAttributesList(): string
    {
        $path = $this->projectDir . '/data/apicepik.json';

        if (!is_file($path)) {
            throw new RuntimeException(sprintf('CEPiK API description not found: %s', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read apicepik.json: %s', $path));
        }
        try {
            $json = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to decode apicepik.json.', 0, $exception);
        }
        $properties = $json['definitions']['VehicleDto']['properties'] ?? null;

        if (!\is_array($properties)) {
            throw new RuntimeException('VehicleDto definition missing in apicepik.json.');
        }

        $lines = [];
        $counter = 0;

        foreach ($properties as $name => $definition) {
            if (!\is_array($definition)) {
                continue;
            }

            $description = trim((string) ($definition['description'] ?? ''));
            $lines[] = sprintf('- %s – %s', (string) $name, $description !== '' ? $description : 'Brak opisu');
            ++$counter;

            if ($counter >= 120) {
                break; // zachowaj rozsądny rozmiar promptu
            }
        }

        return implode("\n", $lines);
    }
}
