<?php

namespace App\Entity;

use App\Repository\ReportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReportRepository::class)]
#[ORM\Table(name: 'reports')]
#[ORM\UniqueConstraint(name: 'reports_code_unique', columns: ['code'])]
#[ORM\HasLifecycleCallbacks]
class Report
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 128)]
    private string $code;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $configSchema;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, ReportRun>
     */
    #[ORM\OneToMany(mappedBy: 'report', targetEntity: ReportRun::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $runs;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
        $this->description = null;
        $this->configSchema = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->runs = new ArrayCollection();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getConfigSchema(): ?array
    {
        return $this->configSchema;
    }

    public function setConfigSchema(?array $configSchema): self
    {
        $this->configSchema = $configSchema;

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
     * @return Collection<int, ReportRun>
     */
    public function getRuns(): Collection
    {
        return $this->runs;
    }

    public function addRun(ReportRun $run): self
    {
        if (!$this->runs->contains($run)) {
            $this->runs->add($run);
            $run->setReport($this);
        }

        return $this;
    }

    public function removeRun(ReportRun $run): self
    {
        if ($this->runs->removeElement($run)) {
            if ($run->getReport() === $this) {
                $run->setReport(null);
            }
        }

        return $this;
    }
}
