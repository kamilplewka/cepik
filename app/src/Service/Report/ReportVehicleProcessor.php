<?php

namespace App\Service\Report;

use App\Entity\ApiErrorLog;
use App\Entity\ReportRun;
use App\Entity\ReportVehicle;
use App\Enum\ReportVehicleStatus;
use App\Exception\CepikClientException;
use App\Repository\ReportVehicleRepository;
use App\Service\CepikClient;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportExceptionInterface;

class ReportVehicleProcessor
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReportVehicleRepository $vehicleRepository,
        private readonly CepikClient $cepikClient,
        ?LoggerInterface $logger = null,
        private readonly int $defaultBatchSize = 10,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function queueVehiclesForRun(ReportRun $run): int
    {
        $vehicleIds = $this->extractVehicleIds($run);

        if ($vehicleIds === []) {
            return 0;
        }

        $existing = $this->vehicleRepository->createQueryBuilder('vehicle')
            ->select('vehicle.vehicleId AS vehicleId')
            ->andWhere('vehicle.reportRun = :run')
            ->setParameter('run', $run)
            ->getQuery()
            ->getScalarResult();

        $existingIds = [];

        foreach ($existing as $row) {
            $existingIds[(string) $row['vehicleId']] = true;
        }

        $created = 0;

        foreach ($vehicleIds as $vehicleId) {
            $vehicleId = (string) $vehicleId;

            if ($vehicleId === '' || isset($existingIds[$vehicleId])) {
                continue;
            }

            $vehicle = new ReportVehicle($vehicleId);
            $vehicle->setStatus(ReportVehicleStatus::Queued);
            $run->addVehicle($vehicle);

            $this->entityManager->persist($vehicle);

            $existingIds[$vehicleId] = true;
            ++$created;
        }

        if ($created > 0) {
            $this->entityManager->flush();
        }

        return $created;
    }

    /**
     * @param callable(int, ?ReportVehicle, int):void|null $progressCallback Receives processed count, current vehicle (null before start) and total
     *
     * @return array{processed:int, halted:bool, halt_reason:?string, retry_pending:bool, failures:int}
     */
    public function processBatch(?int $limit = null, bool $ignoreAttempts = false, ?callable $progressCallback = null): array
    {
        $batchSize = $limit ?? $this->defaultBatchSize;
        $vehicles = $this->vehicleRepository->popBatchForProcessing($batchSize);

        if ($vehicles === []) {
            return [
                'processed' => 0,
                'halted' => false,
                'halt_reason' => null,
                'retry_pending' => false,
                'failures' => 0,
            ];
        }

        $total = \count($vehicles);

        if ($progressCallback) {
            $progressCallback(0, null, $total);
        }

        $processed = 0;
        $halted = false;
        $haltReason = null;
        $retryPending = false;
        $failures = 0;

        foreach ($vehicles as $index => $vehicle) {
            $execution = $this->executeSingleVehicle($vehicle, $ignoreAttempts);
            ++$processed;

            if ($progressCallback) {
                $progressCallback($index + 1, $vehicle, $total);
            }

            if ($execution['halted']) {
                $halted = true;
                $haltReason = $execution['halt_reason'];
                break;
            }

            if ($vehicle->getStatus() === ReportVehicleStatus::Retrying) {
                $retryPending = true;
            }

            if ($vehicle->getStatus() === ReportVehicleStatus::Failed) {
                ++$failures;
            }
        }

        $this->entityManager->flush();

        return [
            'processed' => $processed,
            'halted' => $halted,
            'halt_reason' => $haltReason,
            'retry_pending' => $retryPending,
            'failures' => $failures,
        ];
    }

    private function extractVehicleIds(ReportRun $run): array
    {
        /** @var Connection $connection */
        $connection = $this->entityManager->getConnection();

        $sql = <<<'SQL'
            SELECT DISTINCT elem->>'id' AS vehicle_id
            FROM report_queries rq
                CROSS JOIN LATERAL json_array_elements(rq.response_payload->'data') AS elem
            WHERE rq.report_run_id = :run_id
              AND rq.response_payload IS NOT NULL
        SQL;

        $rows = $connection->fetchFirstColumn($sql, ['run_id' => $run->getId()]);

        return array_values(array_filter($rows, static fn($id) => $id !== null && $id !== ''));
    }

    /**
     * @return array{halted:bool, halt_reason:?string}
     */
    private function executeSingleVehicle(ReportVehicle $vehicle, bool $ignoreAttempts): array
    {
        $vehicle->setStatus(ReportVehicleStatus::InProgress);
        $vehicle->incrementAttempts();

        $this->entityManager->flush();

        $requestOptions = $vehicle->getRequestOptions() ?? [];

        try {
            $response = $this->cepikClient->getVehicle($vehicle->getVehicleId(), $requestOptions);

            if (!\array_key_exists('data', $response)) {
                return $this->handleFailure(
                    $vehicle,
                    'CEPiK vehicle response missing "data" key.',
                    0,
                    [
                        'exception' => 'InvalidResponse',
                        'response' => $response,
                    ],
                    $ignoreAttempts
                );
            }

            $vehicle->setResponsePayload($response);
            $vehicle->setErrorPayload(null);
            $vehicle->setStatus(ReportVehicleStatus::Succeeded);

            return [
                'halted' => false,
                'halt_reason' => null,
            ];
        } catch (CepikClientException|TransportExceptionInterface $exception) {
            return $this->handleFailure(
                $vehicle,
                $exception->getMessage(),
                $exception->getCode(),
                ['exception' => \get_class($exception)],
                $ignoreAttempts
            );
        } catch (\Throwable $throwable) {
            return $this->handleFailure(
                $vehicle,
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
    private function handleFailure(ReportVehicle $vehicle, string $message, int $code, array $context, bool $ignoreAttempts): array
    {
        $this->logger->error('CEPiK vehicle fetch failed', array_merge($context, [
            'vehicle_id' => $vehicle->getVehicleId(),
            'message' => $message,
            'code' => $code,
        ]));

        $vehicle->setErrorPayload([
            'message' => $message,
            'code' => $code,
            'context' => $context,
        ]);

        $hasRetries = $ignoreAttempts || $vehicle->hasRemainingAttempts();
        $vehicle->setStatus($hasRetries ? ReportVehicleStatus::Retrying : ReportVehicleStatus::Failed);

        $log = new ApiErrorLog(sprintf('/pojazdy/%s', $vehicle->getVehicleId()), $vehicle->getRequestOptions() ?? []);
        $log->setReportRun($vehicle->getReportRun());
        $log->setResponseStatus($code > 0 ? $code : null);
        $log->setErrorBody([
            'message' => $message,
            'context' => $context,
        ]);
        $log->setErrorClass($context['exception'] ?? null);

        $this->entityManager->persist($log);

        $haltReason = $this->determineHaltReason($message, $context);

        return [
            'halted' => $haltReason !== null,
            'halt_reason' => $haltReason,
        ];
    }

    private function determineHaltReason(string $message, array $context): ?string
    {
        $lowerMessage = strtolower($message);

        if (str_contains($lowerMessage, 'idle timeout')) {
            return 'Idle timeout reached while fetching vehicle details.';
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
}
