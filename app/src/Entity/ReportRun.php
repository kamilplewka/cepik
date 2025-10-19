<?php

namespace App\Entity;

use App\Enum\ReportRunStatus;
use App\Repository\ReportRunRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRunRepository::class)]
#[ORM\Table(name: 'report_runs')]
#[ORM\HasLifecycleCallbacks]
class ReportRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Report::class, inversedBy: 'runs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Report $report = null;

    #[ORM\Column(type: 'json')]
    private array $inputPayload;

    #[ORM\Column(enumType: ReportRunStatus::class, length: 32)]
    private ReportRunStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $statusMessage = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $queuedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $errorPayload = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, ReportQuery>
     */
    #[ORM\OneToMany(mappedBy: 'reportRun', targetEntity: ReportQuery::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $queries;

    /**
     * @var Collection<int, ReportResult>
     */
    #[ORM\OneToMany(mappedBy: 'reportRun', targetEntity: ReportResult::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $results;

    public function __construct(array $inputPayload)
    {
        $this->inputPayload = $inputPayload;
        $this->status = ReportRunStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->queries = new ArrayCollection();
        $this->results = new ArrayCollection();
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

    public function getReport(): ?Report
    {
        return $this->report;
    }

    public function setReport(?Report $report): self
    {
        $this->report = $report;

        return $this;
    }

    public function getInputPayload(): array
    {
        return $this->inputPayload;
    }

    public function setInputPayload(array $inputPayload): self
    {
        $this->inputPayload = $inputPayload;

        return $this;
    }

    public function getStatus(): ReportRunStatus
    {
        return $this->status;
    }

    public function setStatus(ReportRunStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    public function setStatusMessage(?string $statusMessage): self
    {
        $this->statusMessage = $statusMessage;

        return $this;
    }

    public function getQueuedAt(): ?\DateTimeImmutable
    {
        return $this->queuedAt;
    }

    public function markQueued(): self
    {
        $this->queuedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function markStarted(): self
    {
        $this->startedAt = $this->startedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function markFinished(): self
    {
        $this->finishedAt = new \DateTimeImmutable();

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

    /**
     * @return Collection<int, ReportQuery>
     */
    public function getQueries(): Collection
    {
        return $this->queries;
    }

    public function addQuery(ReportQuery $query): self
    {
        if (!$this->queries->contains($query)) {
            $this->queries->add($query);
            $query->setReportRun($this);
        }

        return $this;
    }

    public function removeQuery(ReportQuery $query): self
    {
        if ($this->queries->removeElement($query)) {
            if ($query->getReportRun() === $this) {
                $query->setReportRun(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ReportResult>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(ReportResult $result): self
    {
        if (!$this->results->contains($result)) {
            $this->results->add($result);
            $result->setReportRun($this);
        }

        return $this;
    }

    public function removeResult(ReportResult $result): self
    {
        if ($this->results->removeElement($result)) {
            if ($result->getReportRun() === $this) {
                $result->setReportRun(null);
            }
        }

        return $this;
    }
}
