<?php

namespace App\Repository;

use App\Entity\ReportRun;
use App\Enum\ReportRunStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReportRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportRun::class);
    }

    /**
     * @return array<int, ReportRun>
     */
    public function findRunsNeedingAggregation(): array
    {
        return $this->createQueryBuilder('run')
            ->andWhere('run.status IN (:statuses)')
            ->setParameter('statuses', [
                ReportRunStatus::Queued,
                ReportRunStatus::Fetching,
            ])
            ->orderBy('run.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
