<?php

namespace App\Message;

class CompetitionSubmittionMessage extends AbstractMessage
{
    private array $formData;
    private int $competitionId;
    private string $email;

    public function __construct(array $formData, int $competitionId, string $email)
    {
        $this->formData = $formData;
        $this->competitionId = $competitionId;
        $this->email = $email;
    }

    public function getCompetitionId(): int
    {
        return $this->competitionId;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
    public function getFormData(): array
    {
        return $this->formData;
    }
}