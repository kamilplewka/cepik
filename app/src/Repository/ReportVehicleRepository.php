<?php

namespace App\Repository;

use App\Entity\ReportRun;
use App\Entity\ReportVehicle;
use App\Enum\ReportVehicleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReportVehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReportVehicle::class);
    }

    /**
     * @return array<int, ReportVehicle>
     */
    public function popBatchForProcessing(int $limit): array
    {
        return $this->createQueryBuilder('vehicle')
            ->andWhere('vehicle.status IN (:statuses)')
            ->setParameter('statuses', [
                ReportVehicleStatus::Pending,
                ReportVehicleStatus::Queued,
                ReportVehicleStatus::Retrying,
            ])
            ->orderBy('vehicle.lastAttemptAt', 'ASC')
            ->addOrderBy('vehicle.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function existsForRunAndVehicleId(ReportRun $run, string $vehicleId): bool
    {
        return $this->createQueryBuilder('vehicle')
            ->select('1')
            ->andWhere('vehicle.reportRun = :run')
            ->andWhere('vehicle.vehicleId = :vehicleId')
            ->setMaxResults(1)
            ->setParameters([
                'run' => $run,
                'vehicleId' => $vehicleId,
            ])
            ->getQuery()
            ->getOneOrNullResult() !== null;
    }
}
