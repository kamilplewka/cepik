<?php

namespace App\Controller;

use App\Dto\ReportPayloadResponse;
use App\Service\Ai\ReportPayloadPlanner;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReportPayloadController
{
    #[Route('/api/reports/nlp/plan', name: 'api_reports_nlp_plan', methods: ['POST'])]
    public function __invoke(Request $request, ReportPayloadPlanner $planner): JsonResponse
    {
        try {
            $payload = $this->decodeRequest($request);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
        $question = trim((string) ($payload['question'] ?? ''));

        if ($question === '') {
            return $this->error('Pole "question" jest wymagane.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $planner->generate($question);
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse($this->formatResponse($question, $result));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRequest(Request $request): array
    {
        $content = $request->getContent() ?: '';

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Niepoprawny JSON wejściowy.');
        }

        return \is_array($decoded) ? $decoded : [];
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => $message,
        ], $status);
    }

    private function formatResponse(string $question, ReportPayloadResponse $response): array
    {
        return [
            'question' => $question,
            'needs_clarification' => $response->needsClarification(),
            'clarifying_questions' => $response->getClarifyingQuestions(),
            'report_payload' => $response->getReportPayload(),
        ];
    }
}
