<?php

namespace App\Entity;

use App\Repository\CompetitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CompetitionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Competition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $prizes = null;

    #[ORM\Column]
    private ?\DateTime $startDate = null;

    #[ORM\Column]
    private ?\DateTime $endDate = null;

    #[ORM\Column]
    private ?int $maxParticipants = null;

    #[ORM\Column(nullable: true)]
    private ?array $formFields = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTime $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'competitions')] # Lazy
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 50)]
    private string $status;

    #[ORM\Column(type: Types::INTEGER)]
    private int $numberOfWinners;

    public function __construct()
    {
        $this->status = self::STATUSES['draft'];
        $this->numberOfWinners = 1;
        $this->maxParticipants = 10000; # Not Used
    }

    public const DEFAULT_FORM_FIELDS = [
        'email' => [
            'type' => 'email',
            'name' => 'email',
            'label' => 'Email'
        ],
        'phoneNumber' => [
            'type' => 'tel',
            'name' => 'phoneNumber',
            'label' => 'Phone Number'
        ],
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'running' => 'Running',
        'submissions_ended' => 'Submissions Ended',
        'winners_generated' => 'Winners Generated',
        'winners_announced' => 'Winners Announced',
        'archived' => 'Archived',
        'cancelled' => 'Cancelled',
    ];
    
    public const PUBLIC_STATUSES = [
        'scheduled',
        'running',
        'submissions_ended',
        'winners_announced',
        'archived',
        'cancelled',
    ];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrizes(): ?string
    {
        return $this->prizes;
    }

    public function setPrizes(string $prizes): static
    {
        $this->prizes = $prizes;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getMaxParticipants(): ?int
    {
        return $this->maxParticipants;
    }

    public function setMaxParticipants(int $maxParticipants): static
    {
        $this->maxParticipants = $maxParticipants;

        return $this;
    }

    public function getFormFields(): ?array
    {
        $this->formFields = $this->formFields ?? [];

        $this->formFields = array_merge(self::DEFAULT_FORM_FIELDS, $this->formFields);

        return $this->formFields;
    }

    public function setFormFields(?array $formFields): static
    {
        $this->formFields = $formFields;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!key_exists($status, self::STATUSES)) {
            throw new \InvalidArgumentException(sprintf('Invalid status: "%s". Must be one of: %s', $status, implode(', ', self::STATUSES)));
        }
        $this->status = $status;

        return $this;
    }

    public function getNumberOfWinners(): ?int
    {
        return $this->numberOfWinners;
    }

    public function setNumberOfWinners(int $numberOfWinners): static
    {
        $this->numberOfWinners = $numberOfWinners;

        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTime(); // Also set updatedAt on initial creation
    }

    /**
     * @ORM\PreUpdate
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
