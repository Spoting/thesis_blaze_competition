<?php
namespace App\MessageHandler;

use App\Message\WinnerTriggerMessage;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class WinnerTriggerMessageHandler
{
    public function __invoke(WinnerTriggerMessage $message)
    {
        $competitionId = $message->getCompetitionId();

        // Your business logic here
        // e.g. trigger winner generation process for the given competitionId

        // For example:
        // $this->winnerService->generateWinners($competitionId);

        // Just a debug log or dump for demo:
        dump(sprintf('Winner generation triggered for competition: %s', $competitionId));
    }
}
