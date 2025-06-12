<?php

namespace App\Message;

/**
 * Message dispatched to trigger the winner generation for a specific competition.
 */
class WinnerTriggerMessage extends AbstractMessage
{
    private int $competitionId;

    public function __construct(int $competitionId)
    {
        $this->competitionId = $competitionId;
    }

    public function getCompetitionId(): int
    {
        return $this->competitionId;
    }

    // TODO: Winners Count?
}