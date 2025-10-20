<?php

namespace App\Repository;

use App\Entity\ReportQuery;
use App\Enum\ReportQueryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReportQueryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportQuery::class);
    }

    /**
     * @return array<int, ReportQuery>
     */
    public function popBatchForProcessing(int $limit): array
    {
        return $this->createQueryBuilder('query')
            ->andWhere('query.status IN (:statuses)')
            ->setParameter('statuses', [
                ReportQueryStatus::Pending,
                ReportQueryStatus::Queued,
                ReportQueryStatus::Retrying,
            ])
            ->orderBy('query.sequence', 'ASC')
            ->addOrderBy('query.lastAttemptAt', 'ASC')
            ->addOrderBy('query.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
