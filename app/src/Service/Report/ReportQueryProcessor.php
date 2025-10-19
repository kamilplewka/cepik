<?php

namespace App\Service\Report;

use App\Entity\ApiErrorLog;
use App\Entity\ReportQuery;
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
     */
    public function processBatch(?int $limit = null, ?callable $progressCallback = null): int
    {
        $batchSize = $limit ?? $this->defaultBatchSize;
        $queries = $this->queryRepository->popBatchForProcessing($batchSize);

        if ($queries === []) {
            return 0;
        }

        $total = \count($queries);

        if ($progressCallback) {
            $progressCallback(0, null, $total);
        }

        foreach ($queries as $index => $query) {
            $this->executeSingleQuery($query);

            if ($progressCallback) {
                $progressCallback($index + 1, $query, $total);
            }
        }

        $this->entityManager->flush();

        return \count($queries);
    }

    private function executeSingleQuery(ReportQuery $query): void
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
                $this->handleFailure(
                    $query,
                    'CEPiK response missing "data" key.',
                    0,
                    [
                        'exception' => 'InvalidResponse',
                        'response' => $response,
                    ]
                );

                return;
            }

            $query->setResponsePayload($response);
            $query->setAggregatedCount($count);
            $query->setErrorPayload(null);
            $query->setStatus(ReportQueryStatus::Succeeded);
        } catch (CepikClientException|TransportExceptionInterface $exception) {
            $this->handleFailure($query, $exception->getMessage(), $exception->getCode(), ['exception' => \get_class($exception)]);
        } catch (\Throwable $throwable) {
            $this->handleFailure($query, $throwable->getMessage(), $throwable->getCode(), ['exception' => \get_class($throwable)]);
        }
    }

    private function handleFailure(ReportQuery $query, string $message, int $code, array $context = []): void
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

        $hasRetries = $query->hasRemainingAttempts();
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

        if (!$hasRetries) {
            $run = $query->getReportRun();
            $run->setStatus(ReportRunStatus::Failed);
            $run->setErrorPayload([
                'message' => $message,
                'code' => $code,
            ]);
            $run->setStatusMessage('Run failed due to exhausted query retries.');
            $run->markFinished();
        }
    }
}
