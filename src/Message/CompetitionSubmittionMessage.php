<?php

namespace App\Message;

class CompetitionSubmittionMessage extends AbstractMessage
{
    private array $formData;
    private int $competitionId;
    private string $email;
    private string $phoneNumber;
    private bool $premium;

    public function __construct(array $formData, int $competitionId, string $email, string $phoneNumber, bool $premium = false)
    {
        $this->formData = $formData;
        $this->competitionId = $competitionId;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->premium = $premium;
    }


    public function getCompetitionId(): int
    {
        return $this->competitionId;
    }
    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }
    public function getFormData(): array
    {
        return $this->formData;
    }

    public function isPremium(): bool
    {
        return $this->premium;
    }
}