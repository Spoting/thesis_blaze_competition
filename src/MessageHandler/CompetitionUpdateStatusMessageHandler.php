<?php

namespace App\MessageHandler;

use App\Message\CompetitionUpdateStatusMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CompetitionUpdateStatusMessageHandler
{
    public function __invoke(CompetitionUpdateStatusMessage $message): void
    {
        $competitionId = $message->getCompetitionId();

        dump(sprintf('Status Update triggered for competition: %s', $competitionId));
        dump(
            $message->getTargetStatus()
                . " | " . $message->getMessageCreationDate()
                . " | " . $message->getDelayTime()
        );
    }
}
