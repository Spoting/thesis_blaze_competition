<?php

namespace App\MessageHandler;

use App\Entity\Competition;
use App\Message\CompetitionUpdateStatusMessage;
use App\Service\CompetitionService;
use App\Service\MessageProducerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CompetitionUpdateStatusMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private MessageProducerService $messageProducerService,
        private CompetitionService $competitionService
    ) {}

    public function __invoke(CompetitionUpdateStatusMessage $message): void
    {
        $competitionId = $message->getCompetitionId();
        $targetStatus = $message->getTargetStatus();
        $messageCreationDate = $message->getMessageCreationDate();
        $delayTime = $message->getDelayTime();

        $this->logger->info(sprintf(
            'Attempting status update for competition ID: %s. Target Status: %s. Message created: %s. Delayed by: %d seconds.',
            $competitionId,
            $targetStatus,
            $messageCreationDate,
            $delayTime
        ));

        dump(sprintf('Status Update triggered for competition: %s', $competitionId));
        dump(
            $message->getTargetStatus()
                . " | " . $message->getMessageCreationDate()
                . " | " . $message->getDelayTime()
        );

        /** @var Competition */
        $competition = $this->entityManager->getRepository(Competition::class)->find($competitionId);

        // Validate Status Change
        $isStatusValid = $this->competitionService->isStatusTransitionValid($competition, $targetStatus);
        if (!$isStatusValid) {
            // throw exception. dont attempt retry
        }

        // Update Competition
        $competition->setStatus($targetStatus);
        $this->entityManager->flush();

        // Publish Message Email Notification to Organizer
        $organizerEmail = $competition->getCreatedBy()?->getEmail();
        $emailSubject = 'Notification: Competition Status Update';
        $emailText = 'Status Update - ' . $competition->getTitle() . ' is ' . Competition::STATUSES[$competition->getStatus()];
        if (!empty($organizerEmail)) {
            $this->messageProducerService->produceEmailNotificationMessage(
                $competitionId,
                $organizerEmail,
                $emailSubject,
                ['text' => $emailText]
            );
        }


        dump(sprintf('Status Update Done for competition: %s', $competitionId));
    }
}
