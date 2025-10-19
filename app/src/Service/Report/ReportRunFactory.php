<?php

namespace App\Service\Report;

use App\Entity\Report;
use App\Entity\ReportRun;
use App\Enum\ReportRunStatus;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class ReportRunFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReportRepository $reportRepository,
    ) {
    }

    public function createRun(string $reportCode, array $inputPayload, ?string $reportName = null, ?string $description = null): ReportRun
    {
        if ($reportCode === '') {
            throw new InvalidArgumentException('Report code cannot be empty.');
        }

        $report = $this->reportRepository->findOneBy(['code' => $reportCode]);

        if (!$report) {
            $report = new Report($reportCode, $reportName ?? ucfirst(str_replace('-', ' ', $reportCode)));
            $report->setDescription($description);
            $this->entityManager->persist($report);
        }

        $run = new ReportRun($inputPayload);
        $report->addRun($run);
        $run->setStatus(ReportRunStatus::Pending);

        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }
}
