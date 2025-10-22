<?php

namespace App\Service\Report;

use App\Entity\ApiErrorLog;
use App\Entity\ReportQuery;
use App\Entity\ReportRun;
use App\Enum\ReportQueryStatus;
use App\Enum\ReportRunStatus;
use App\Exception\CepikClientException;
use App\Repository\ReportQueryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;

class ReportQueryProcessor
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ReportQueryRepository $queryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly \App\Service\CepikClient $cepikClient,
        ?LoggerInterface $logger = null,
        private readonly int $defaultBatchSize = 10,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param callable(int, ?ReportQuery, int):void|null $progressCallback Receives processed count, current query (null before start) and total
     *
     * @return array{processed:int, halted:bool, halt_reason:?string}
     */
    public function processBatch(?int $limit = null, bool $ignoreAttempts = false, ?callable $progressCallback = null): array
    {
        $batchSize = $limit ?? $this->defaultBatchSize;
        $queries = $this->queryRepository->popBatchForProcessing($batchSize);

        if ($queries === []) {
            return [
                'processed' => 0,
                'halted' => false,
                'halt_reason' => null,
            ];
        }

        $total = \count($queries);

        if ($progressCallback) {
            $progressCallback(0, null, $total);
        }

        $processed = 0;
        $halted = false;
        $haltReason = null;

        foreach ($queries as $index => $query) {
            $execution = $this->executeSingleQuery($query, $ignoreAttempts);
            ++$processed;

            if ($progressCallback) {
                $progressCallback($index + 1, $query, $total);
            }

            if ($execution['halted']) {
                $halted = true;
                $haltReason = $execution['halt_reason'];
                break;
            }
        }

        $this->entityManager->flush();

        return [
            'processed' => $processed,
            'halted' => $halted,
            'halt_reason' => $haltReason,
        ];
    }

    /**
     * @return array{halted:bool, halt_reason:?string}
     */
    private function executeSingleQuery(ReportQuery $query, bool $ignoreAttempts): array
    {
        $run = $query->getReportRun();

        if ($run->getStatus() === ReportRunStatus::Queued) {
            $run->setStatus(ReportRunStatus::Fetching);
            $run->markStarted();
        }

        $query->setStatus(ReportQueryStatus::InProgress);
        $query->incrementAttempts();

        $this->entityManager->flush();

        $requestPayload = $query->getRequestParams();
        $queryParams = $requestPayload['query'] ?? $requestPayload;

        try {
            $response = $this->cepikClient->listVehicles($queryParams);
            $meta = $response['meta'] ?? [];
            $count = isset($meta['count']) ? (int) $meta['count'] : null;

            if (!\array_key_exists('data', $response)) {
                return $this->handleFailure(
                    $query,
                    'CEPiK response missing "data" key.',
                    0,
                    [
                        'exception' => 'InvalidResponse',
                        'response' => $response,
                    ],
                    $ignoreAttempts
                );
            }

            $query->setResponsePayload($response);
            $query->setAggregatedCount($count);
            $query->setErrorPayload(null);
            $query->setStatus(ReportQueryStatus::Succeeded);

            $total = $this->extractTotalFromMeta($meta);

            if ($total !== null) {
                $this->schedulePaginationQueries($query, $total);
            }

            return [
                'halted' => false,
                'halt_reason' => null,
            ];
        } catch (CepikClientException|TransportExceptionInterface $exception) {
            return $this->handleFailure(
                $query,
                $exception->getMessage(),
                $exception->getCode(),
                ['exception' => \get_class($exception)],
                $ignoreAttempts
            );
        } catch (\Throwable $throwable) {
            return $this->handleFailure(
                $query,
                $throwable->getMessage(),
                $throwable->getCode(),
                ['exception' => \get_class($throwable)],
                $ignoreAttempts
            );
        }
    }

    /**
     * @return array{halted:bool, halt_reason:?string}
     */
    private function handleFailure(ReportQuery $query, string $message, int $code, array $context, bool $ignoreAttempts): array
    {
        $this->logger->error('CEPiK query failed', array_merge($context, [
            'query_id' => $query->getId(),
            'message' => $message,
            'code' => $code,
        ]));

        $query->setErrorPayload([
            'message' => $message,
            'code' => $code,
            'context' => $context,
        ]);

        $hasRetries = $ignoreAttempts || $query->hasRemainingAttempts();
        $query->setStatus($hasRetries ? ReportQueryStatus::Retrying : ReportQueryStatus::Failed);

        $log = new ApiErrorLog('/pojazdy', $query->getRequestParams());
        $log->setReportRun($query->getReportRun());
        $log->setReportQuery($query);
        $log->setResponseStatus($code > 0 ? $code : null);
        $log->setErrorBody([
            'message' => $message,
            'context' => $context,
        ]);
        $log->setErrorClass($context['exception'] ?? null);

        $this->entityManager->persist($log);

        $haltReason = $this->determineHaltReason($message, $context);

        if (!$hasRetries && !$ignoreAttempts) {
            $run = $query->getReportRun();
            $run->setStatus(ReportRunStatus::Failed);
            $run->setErrorPayload([
                'message' => $message,
                'code' => $code,
            ]);
            $run->setStatusMessage('Run failed due to exhausted query retries.');
            $run->markFinished();
        }

        if ($haltReason) {
            $run = $query->getReportRun();
            $run->setStatusMessage($haltReason);
        }

        return [
            'halted' => $haltReason !== null,
            'halt_reason' => $haltReason,
        ];
    }

    private function determineHaltReason(string $message, array $context): ?string
    {
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'idle timeout')) {
            return 'Idle timeout reached while querying CEPiK.';
        }

        $response = $context['response'] ?? null;

        if (\is_array($response) && isset($response['errors']) && \is_array($response['errors'])) {
            foreach ($response['errors'] as $error) {
                if (($error['error-code'] ?? null) === 'API-ERR-100011') {
                    return 'CEPiK API limit reached (API-ERR-100011).';
                }
            }
        }

        return null;
    }

    private function schedulePaginationQueries(ReportQuery $query, int $total): void
    {
        $requestPayload = $query->getRequestParams();
        $queryParams = $requestPayload['query'] ?? $requestPayload;

        $limit = isset($queryParams['limit']) ? (int) $queryParams['limit'] : null;
        $limit = $limit !== null && $limit > 0 ? $limit : null;

        if ($limit === null) {
            return;
        }

        $currentPage = isset($queryParams['page']) ? (int) $queryParams['page'] : 1;
        $totalPages = (int) ceil($total / $limit);

        if ($totalPages <= $currentPage) {
            return;
        }

        $run = $query->getReportRun();

        $existingPages = $this->collectExistingPages($run, $queryParams);

        $nextSequence = $this->findNextSequence($run);

        for ($page = $currentPage + 1; $page <= $totalPages; ++$page) {
            if (isset($existingPages[$page])) {
                continue;
            }

            $newQueryParams = $queryParams;
            $newQueryParams['page'] = $page;

            if (isset($requestPayload['query'])) {
                $newRequestPayload = $requestPayload;
                $newRequestPayload['query'] = $newQueryParams;
            } else {
                $newRequestPayload = $newQueryParams;
            }

            $meta = $newRequestPayload['meta'] ?? [];
            $meta['page'] = $page;
            $newRequestPayload['meta'] = $meta;

            $newQuery = new ReportQuery($nextSequence++, $newRequestPayload);
            $newQuery->setStatus(ReportQueryStatus::Pending);

            $run->addQuery($newQuery);
            $this->entityManager->persist($newQuery);
        }
    }

    /**
     * @return array<int, true>
     */
    private function collectExistingPages(ReportRun $run, array $queryParams): array
    {
        $signature = $this->buildQuerySignature($queryParams);
        $pages = [];

        foreach ($run->getQueries() as $existingQuery) {
            $existingPayload = $existingQuery->getRequestParams();
            $existingParams = $existingPayload['query'] ?? $existingPayload;

            if ($this->buildQuerySignature($existingParams) !== $signature) {
                continue;
            }

            $page = isset($existingParams['page']) ? (int) $existingParams['page'] : 1;
            $pages[$page] = true;
        }

        return $pages;
    }

    private function buildQuerySignature(array $queryParams): string
    {
        $normalized = $this->normalizeParams($queryParams);
        unset($normalized['page']);

        return md5((string) json_encode($normalized));
    }

    private function findNextSequence(ReportRun $run): int
    {
        $max = 0;

        foreach ($run->getQueries() as $existing) {
            $max = max($max, $existing->getSequence());
        }

        return $max + 1;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function normalizeParams(array $params): array
    {
        ksort($params);

        foreach ($params as $key => $value) {
            if (\is_array($value)) {
                $params[$key] = $this->normalizeParams($value);
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function extractTotalFromMeta(array $meta): ?int
    {
        foreach (['total', 'total-count', 'totalCount'] as $key) {
            if (isset($meta[$key]) && is_numeric($meta[$key])) {
                return (int) $meta[$key];
            }
        }

        $pagination = $meta['pagination'] ?? null;

        if (\is_array($pagination) && isset($pagination['total']) && is_numeric($pagination['total'])) {
            return (int) $pagination['total'];
        }

        return null;
    }
}
