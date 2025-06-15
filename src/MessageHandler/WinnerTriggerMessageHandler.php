<?php
namespace App\MessageHandler;

use App\Entity\Competition;
use App\Message\WinnerTriggerMessage;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class WinnerTriggerMessageHandler
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

        // Your business logic here
        // e.g. trigger winner generation process for the given competitionId

        // For example:
        // $this->winnerService->generateWinners($competitionId);

        dump(sprintf('Winner generation triggered for competition: %s', $competitionId));
    }
}
