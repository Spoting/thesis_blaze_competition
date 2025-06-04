<?php

namespace App\Message;

class CompetitionSubmittionMessage
{
    private array $formData;
    private int $competitionId;
    private string $email;
    private string $phoneNumber;

    public function __construct(array $formData, int $competitionId, string $email, string $phoneNumber)
    {
        $this->formData = $formData;
        $this->competitionId = $competitionId;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
    }

    public function getFormData(): array
    {
        return $this->formData;
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
}