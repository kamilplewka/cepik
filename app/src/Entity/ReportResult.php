<?php

namespace App\Entity;

use App\Enum\ReportResultStatus;
use App\Repository\ReportResultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportResultRepository::class)]
#[ORM\Table(name: 'report_results')]
#[ORM\HasLifecycleCallbacks]
class ReportResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: ReportRun::class, inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ReportRun $reportRun = null;

    #[ORM\Column(enumType: ReportResultStatus::class, length: 32)]
    private ReportResultStatus $status = ReportResultStatus::Pending;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $resultPayload = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getReportRun(): ?ReportRun
    {
        return $this->reportRun;
    }

    public function setReportRun(?ReportRun $reportRun): self
    {
        $this->reportRun = $reportRun;

        return $this;
    }

    public function getStatus(): ReportResultStatus
    {
        return $this->status;
    }

    public function setStatus(ReportResultStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getResultPayload(): ?array
    {
        return $this->resultPayload;
    }

    public function setResultPayload(?array $resultPayload): self
    {
        $this->resultPayload = $resultPayload;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
