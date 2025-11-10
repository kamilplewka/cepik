<?php

namespace App\Service\Report;

use App\Entity\ReportQuery;
use App\Entity\ReportRun;
use App\Enum\ReportQueryStatus;
use App\Enum\ReportRunStatus;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class ReportQueryGenerator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param callable(int, array<string, mixed>):void|null $progressCallback
     */
    public function generateForRun(ReportRun $run, ?callable $progressCallback = null): int
    {
        $run->setStatus(ReportRunStatus::BuildingQueries);

        $payload = $run->getInputPayload();

        $regions = $payload['regions'] ?? null;
        $years = $payload['years'] ?? null;
        $dateWindows = $payload['date_windows'] ?? null;
        $filters = $payload['filters'] ?? [];
        $options = $payload['options'] ?? [];

        if (!\is_array($regions) || $regions === []) {
            throw new InvalidArgumentException('Input payload must contain a non-empty "regions" array.');
        }

        if (!\is_array($years) || $years === []) {
            throw new InvalidArgumentException('Input payload must contain a non-empty "years" array.');
        }

        if (!\is_array($dateWindows) || $dateWindows === []) {
            throw new InvalidArgumentException('Input payload must contain a non-empty "date_windows" array.');
        }

        $queryLimit = $options['limit'] ?? 500;
        $queryOptions = array_merge([
            'tylko-zarejestrowane' => true,
            'limit' => $queryLimit,
            'page' => 1,
            'typ-daty' => $options['typ-daty'] ?? '1',
        ], $options);

        $sequence = 1;
        $created = 0;

        foreach ($dateWindows as $window) {
            if (!\is_array($window) || !isset($window['from'])) {
                throw new InvalidArgumentException('Each date window must define at least a "from" key.');
            }

            foreach ($regions as $region) {
                $regionCode = (string) $region;

                foreach ($years as $year) {
                    $yearValue = (string) $year;

                    $queryParams = $this->buildQueryParams($regionCode, $yearValue, $window, $filters, $queryOptions);

                    $requestPayload = [
                        'query' => $queryParams,
                        'meta' => [
                            'wojewodztwo' => $regionCode,
                            'year' => $yearValue,
                            'date_window' => $window,
                            'page' => $queryParams['page'] ?? 1,
                        ],
                    ];

                    $query = new ReportQuery($sequence++, $requestPayload);
                    $query->setStatus(ReportQueryStatus::Pending);

                    $this->entityManager->persist($query);
                    $run->addQuery($query);
                    ++$created;

                    if ($progressCallback) {
                        $progressCallback($created, [
                            'sequence' => $query->getSequence(),
                            'region' => $regionCode,
                            'year' => $yearValue,
                            'window' => $window,
                        ]);
                    }
                }
            }
        }

        $run->setStatus(ReportRunStatus::Queued);
        $run->markQueued();

        $this->entityManager->flush();

        return $created;
    }

    /**
     * @param array<string, mixed> $window
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function buildQueryParams(string $regionCode, string $yearValue, array $window, array $filters, array $options): array
    {
        $query = array_merge($options, [
            'wojewodztwo' => $regionCode,
            'data-od' => $window['from'],
        ]);

        if (isset($window['to'])) {
            $query['data-do'] = $window['to'];
        }

        foreach ($filters as $key => $value) {
            $query[sprintf('filter[%s]', $key)] = $value;
        }

        $query['filter[rok-produkcji]'] = $yearValue;

        return $query;
    }
}
