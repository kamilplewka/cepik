<?php

namespace App\Entity;

use App\Repository\ApiErrorLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiErrorLogRepository::class)]
#[ORM\Table(name: 'api_error_logs')]
#[ORM\Index(columns: ['created_at'], name: 'api_error_logs_created_at_idx')]
class ApiErrorLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\ManyToOne(targetEntity: ReportRun::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ReportRun $reportRun = null;

    #[ORM\ManyToOne(targetEntity: ReportQuery::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ReportQuery $reportQuery = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $endpoint;

    #[ORM\Column(type: 'json')]
    private array $requestParams;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $responseStatus = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $errorBody = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $errorClass = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $endpoint, array $requestParams)
    {
        $this->endpoint = $endpoint;
        $this->requestParams = $requestParams;
        $this->createdAt = new \DateTimeImmutable();
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

    public function getReportQuery(): ?ReportQuery
    {
        return $this->reportQuery;
    }

    public function setReportQuery(?ReportQuery $reportQuery): self
    {
        $this->reportQuery = $reportQuery;

        return $this;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getRequestParams(): array
    {
        return $this->requestParams;
    }

    public function getResponseStatus(): ?int
    {
        return $this->responseStatus;
    }

    public function setResponseStatus(?int $responseStatus): self
    {
        $this->responseStatus = $responseStatus;

        return $this;
    }

    public function getErrorBody(): ?array
    {
        return $this->errorBody;
    }

    public function setErrorBody(?array $errorBody): self
    {
        $this->errorBody = $errorBody;

        return $this;
    }

    public function getErrorClass(): ?string
    {
        return $this->errorClass;
    }

    public function setErrorClass(?string $errorClass): self
    {
        $this->errorClass = $errorClass;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
