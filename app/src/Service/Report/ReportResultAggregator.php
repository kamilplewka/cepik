<?php

namespace App\Service\Report;

use App\Entity\ReportQuery;
use App\Entity\ReportResult;
use App\Entity\ReportRun;
use App\Enum\ReportQueryStatus;
use App\Enum\ReportResultStatus;
use App\Enum\ReportRunStatus;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class ReportResultAggregator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param callable(int, ?ReportQuery, int):void|null $progressCallback Receives processed count, current query (null before start) and total
     */
    public function aggregateRun(ReportRun $run, ?callable $progressCallback = null): ReportResult
    {
        if ($run->getQueries()->isEmpty()) {
            throw new InvalidArgumentException('Cannot aggregate a report run without generated queries.');
        }

        foreach ($run->getQueries() as $query) {
            if (!\in_array($query->getStatus(), [ReportQueryStatus::Succeeded, ReportQueryStatus::Skipped], true)) {
                throw new InvalidArgumentException(sprintf('Query #%d is not ready for aggregation (status: %s).', $query->getId(), $query->getStatus()->value));
            }
        }

        $run->setStatus(ReportRunStatus::Aggregating);

        $result = $run->getResults()->first() ?: null;

        if (!$result instanceof ReportResult) {
            $result = new ReportResult();
            $result->setReportRun($run);
            $this->entityManager->persist($result);
            $run->addResult($result);
        }

        $result->setStatus(ReportResultStatus::Calculating);

        $aggregation = $this->buildAggregation($run, $progressCallback);

        $result->setResultPayload($aggregation);
        $result->setStatus(ReportResultStatus::Ready);

        $run->setStatus(ReportRunStatus::Completed);
        $run->markFinished();

        $this->entityManager->flush();

        return $result;
    }

    /**
     * @param callable(int, ?ReportQuery, int):void|null $progressCallback
     *
     * @return array<string, mixed>
     */
    private function buildAggregation(ReportRun $run, ?callable $progressCallback = null): array
    {
        $total = 0;
        $byRegion = [];

        $totalQueries = $run->getQueries()->count();

        if ($progressCallback) {
            $progressCallback(0, null, $totalQueries);
        }

        /** @var ReportQuery $query */
        foreach ($run->getQueries() as $index => $query) {
            $request = $query->getRequestParams();
            $meta = $request['meta'] ?? [];
            $queryParams = $request['query'] ?? $request;

            $region = (string) ($meta['wojewodztwo'] ?? ($queryParams['wojewodztwo'] ?? 'unknown'));
            $year = (string) ($meta['year'] ?? $this->extractYearFromParams($queryParams) ?? 'unknown');
            $window = $meta['date_window'] ?? [
                'from' => $queryParams['data-od'] ?? null,
                'to' => $queryParams['data-do'] ?? null,
            ];

            $count = $query->getAggregatedCount() ?? 0;
            $total += $count;

            if (!isset($byRegion[$region])) {
                $byRegion[$region] = [
                    'total' => 0,
                    'by_year' => [],
                ];
            }

            if (!isset($byRegion[$region]['by_year'][$year])) {
                $byRegion[$region]['by_year'][$year] = [
                    'total' => 0,
                    'windows' => [],
                ];
            }

            $byRegion[$region]['total'] += $count;
            $byRegion[$region]['by_year'][$year]['total'] += $count;
            $windowKey = sprintf('%s_%s', $window['from'] ?? 'start', $window['to'] ?? 'end');
            $byRegion[$region]['by_year'][$year]['windows'][$windowKey] = ($byRegion[$region]['by_year'][$year]['windows'][$windowKey] ?? 0) + $count;

            if ($progressCallback) {
                $progressCallback($index + 1, $query, $totalQueries);
            }
        }

        return [
            'total' => $total,
            'by_region' => $byRegion,
            'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function extractYearFromParams(array $queryParams): ?string
    {
        foreach ($queryParams as $key => $value) {
            if ($key === 'filter[rok-produkcji]') {
                return (string) $value;
            }
        }

        return null;
    }
}
