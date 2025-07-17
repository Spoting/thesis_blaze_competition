<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('competition_status_amqp')]
final class CompetitionUpdateStatusMessage extends AbstractStatusMessage
{
    private int $competitionId;
    private string $targetStatus;

    public function __construct(int $competitionId, string $targetStatus, string $messageCreationDate, int $delayTime, string $organizerEmail)
    {
        parent::__construct($messageCreationDate, $delayTime, $organizerEmail);
        $this->competitionId = $competitionId;
        $this->targetStatus = $targetStatus;
    }

    public function getCompetitionId(): int
    {
        return $this->competitionId;
    }

    public function getTargetStatus(): string
    {
        return $this->targetStatus;
    }
}
