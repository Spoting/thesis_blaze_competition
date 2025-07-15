<?php

namespace App\Entity;

use App\Repository\CompetitionStatsSnapshotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompetitionStatsSnapshotRepository::class)]
#[ORM\Index(name: 'idx_snapshot_competition_timestamp', columns: ['competition_id', 'captured_at'])]
class CompetitionStatsSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $initiatedSubmissions = null;
    
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $processedSubmissions = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $failedSubmissions = null;
    
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Competition $competition = null;
    
    #[ORM\Column]
    private ?\DateTimeImmutable $capturedAt = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompetition(): ?Competition
    {
        return $this->competition;
    }

    public function setCompetition(?Competition $competition): static
    {
        $this->competition = $competition;

        return $this;
    }

    public function getCapturedAt(): ?\DateTimeImmutable
    {
        return $this->capturedAt;
    }

    public function setCapturedAt(\DateTimeImmutable $capturedAt): static
    {
        $this->capturedAt = $capturedAt;

        return $this;
    }

    public function getInitiatedSubmissions(): ?int
    {
        return $this->initiatedSubmissions;
    }

    public function setInitiatedSubmissions(int $initiatedSubmissions): static
    {
        $this->initiatedSubmissions = $initiatedSubmissions;

        return $this;
    }

    public function getProcessedSubmissions(): ?int
    {
        return $this->processedSubmissions;
    }

    public function setProcessedSubmissions(int $processedSubmissions): static
    {
        $this->processedSubmissions = $processedSubmissions;

        return $this;
    }

    public function getFailedSubmissions(): ?int
    {
        return $this->failedSubmissions;
    }

    public function setFailedSubmissions(int $failedSubmissions): static
    {
        $this->failedSubmissions = $failedSubmissions;

        return $this;
    }
}
