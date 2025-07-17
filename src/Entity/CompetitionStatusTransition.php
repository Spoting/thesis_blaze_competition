<?php

namespace App\Entity;

use App\Repository\CompetitionStatusTransitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompetitionStatusTransitionRepository::class)]
#[ORM\Index(name: 'idx_status_transition_competition_timestamp', columns: ['competition_id', 'transitioned_at'])]
class CompetitionStatusTransition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Competition::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] // Cascade deletion if competition is removed
    private ?Competition $competition = null;

    #[ORM\Column(length: 50)]
    private ?string $oldStatus = null;

    #[ORM\Column(length: 50)]
    private ?string $newStatus = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $transitionedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $triggeredBy = null;

    public function __construct()
    {
        $this->transitionedAt = new \DateTimeImmutable();
    }

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

    public function getOldStatus(): ?string
    {
        return $this->oldStatus;
    }

    public function setOldStatus(string $oldStatus): static
    {
        $this->oldStatus = $oldStatus;

        return $this;
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    public function setNewStatus(string $newStatus): static
    {
        $this->newStatus = $newStatus;

        return $this;
    }

    public function getTransitionedAt(): ?\DateTimeImmutable
    {
        return $this->transitionedAt;
    }

    public function setTransitionedAt(\DateTimeImmutable $transitionedAt): static
    {
        $this->transitionedAt = $transitionedAt;

        return $this;
    }

    public function getTriggeredBy(): ?string
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(?string $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }
}
