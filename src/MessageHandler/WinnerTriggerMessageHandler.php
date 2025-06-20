<?php

namespace App\MessageHandler;

use App\Message\WinnerTriggerMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WinnerTriggerMessageHandler
{
    private EntityManagerInterface $entityManager;

    // Inject the EntityManagerInterface via the constructor
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(WinnerTriggerMessage $message)
    {
        $competitionId = $message->getCompetitionId();

        // $this->winnerService->generateWinners($competitionId);

        dump(sprintf('Winner generation triggered for competition: %s', $competitionId));
        dump($message->getMessageCreationDate()
            . " | " . $message->getDelayTime());
    }
}
