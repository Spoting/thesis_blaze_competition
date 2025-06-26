<?php

namespace App\MessageHandler;

use App\Constants\AppConstants;
use App\Entity\Competition;
use App\Message\CompetitionUpdateStatusMessage;
use App\Message\EmailNotificationMessage;
use App\Service\CompetitionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CompetitionUpdateStatusMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager,
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

        // 2. Update Competition
        $competition->setStatus($targetStatus);
        $this->entityManager->flush();

        // 3. Publish Message Email Notification to Organizer
        $organizerEmail = $competition->getCreatedBy()?->getEmail();
        $emailSubject = 'Notification: Competition Status Update';
        $emailText = 'Status Update - ' . $competition->getTitle() . ' is ' . $competition->getStatus();
        if (!empty($organizerEmail)) {
            $message = new EmailNotificationMessage(
                $competitionId,
                $organizerEmail,
                $emailSubject,
                templateContext: ['text' => $emailText]
            );

            $this->messageBus->dispatch(
                $message,
                [new AmqpStamp(
                    AppConstants::AMPQ_ROUTING['email_notification'],
                    attributes: [
                        'content_type' => 'application/json',
                        'content_encoding' => 'utf-8',
                    ]
                )]
            );
        }


        dump(sprintf('Status Update Done for competition: %s', $competitionId));
    }
}
