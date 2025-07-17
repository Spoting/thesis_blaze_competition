<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Message dispatched to trigger the winner generation for a specific competition.
 */
#[AsMessage('competition_status_amqp')]
final class WinnerTriggerMessage extends AbstractStatusMessage
{
    private int $competitionId;

    public function __construct(int $competitionId, string $messageCreationDate, int $delayTime, string $organizerEmail)
    {
        parent::__construct($messageCreationDate, $delayTime, $organizerEmail);
        $this->competitionId = $competitionId;
    }

    public function getCompetitionId(): int
    {
        return $this->competitionId;
    }
}
