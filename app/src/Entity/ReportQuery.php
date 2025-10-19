<?php

namespace App\Entity;

use App\Enum\ReportQueryStatus;
use App\Repository\ReportQueryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportQueryRepository::class)]
#[ORM\Table(name: 'report_queries')]
#[ORM\HasLifecycleCallbacks]
class ReportQuery
{
    private const DEFAULT_MAX_ATTEMPTS = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: ReportRun::class, inversedBy: 'queries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ReportRun $reportRun = null;

    #[ORM\Column(type: 'integer')]
    private int $sequence;

    #[ORM\Column(type: 'json')]
    private array $requestParams;

    #[ORM\Column(enumType: ReportQueryStatus::class, length: 32)]
    private ReportQueryStatus $status = ReportQueryStatus::Pending;

    #[ORM\Column(type: 'smallint')]
    private int $attempts = 0;

    #[ORM\Column(type: 'smallint')]
    private int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $responsePayload = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $aggregatedCount = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $errorPayload = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(int $sequence, array $requestParams)
    {
        $this->sequence = $sequence;
        $this->requestParams = $requestParams;
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

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getRequestParams(): array
    {
        return $this->requestParams;
    }

    public function setRequestParams(array $requestParams): self
    {
        $this->requestParams = $requestParams;

        return $this;
    }

    public function getStatus(): ReportQueryStatus
    {
        return $this->status;
    }

    public function setStatus(ReportQueryStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): self
    {
        ++$this->attempts;
        $this->lastAttemptAt = new \DateTimeImmutable();

        return $this;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    public function setResponsePayload(?array $responsePayload): self
    {
        $this->responsePayload = $responsePayload;

        return $this;
    }

    public function getAggregatedCount(): ?int
    {
        return $this->aggregatedCount;
    }

    public function setAggregatedCount(?int $aggregatedCount): self
    {
        $this->aggregatedCount = $aggregatedCount;

        return $this;
    }

    public function getErrorPayload(): ?array
    {
        return $this->errorPayload;
    }

    public function setErrorPayload(?array $errorPayload): self
    {
        $this->errorPayload = $errorPayload;

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

    public function hasRemainingAttempts(): bool
    {
        return $this->attempts < $this->maxAttempts;
    }
}
