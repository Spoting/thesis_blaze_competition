<?php

namespace App\EventSubscriber;

use App\Entity\Competition;
use App\Message\CompetitionSubmittionMessage;
use App\Message\CompetitionUpdateStatusMessage;
use App\Message\WinnerTriggerMessage;
use App\Messenger\DynamicCompetitionDlqSender;
use App\Repository\CompetitionRepository;
use App\Service\MessageProducerService;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;


class WorkerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DynamicCompetitionDlqSender $dlqSender,
        private MessageProducerService $messageProducer,
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private CompetitionRepository $competitionRepository,
    ) {}

    public function onSubmissionMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return; // Not yet time to dead-letter
        }

        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();
        if (!($message instanceof CompetitionSubmittionMessage)) {
            return;
        }

        // Root Failed Message to DLQ
        $queueName = 'dlq_competition_submission_' . $message->getCompetitionId();
        $this->dlqSender->send($queueName, $message);

        // Decrement the Total Count for this Competition
        $count_key = $this->redisKeyBuilder->getCompetitionCountKey($message->getCompetitionId());
        $this->redisManager->decrementValue($count_key);

        // Sent Email
        $emailSubject = 'Failed Submission for ' . $message->getCompetitionId();
        $emailText = 'We are sorry, there is been a Server Error. Your Submission Failed for Competition: ' . $message->getCompetitionId();
        $this->messageProducer->produceEmailNotificationMessage(
            $message->getCompetitionId(),
            $message->getEmail(),
            $emailSubject,
            ['text' => $emailText]
        );
    }


    public function onWinnerTriggerMessageFailed(WorkerMessageFailedEvent $event): void
    {

        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();
        if (!($message instanceof WinnerTriggerMessage)) {
            return;
        }

        /** @var Competition */
        $competition = $this->competitionRepository->find($message->getCompetitionId());

        if ($event->willRetry()) {
            // Publish RETRY Message Email Notification to Organizer
            $organizerEmail = $competition->getCreatedBy()?->getEmail();
            $emailSubject = 'Notification: RETRYING to Generate Winners for Competition ' . $competition->getId();
            $emailText = $competition->getId() . ' is RETRYING to Generate Winners...! <br> We are sorry for the delay';
            if (!empty($organizerEmail)) {
                $this->messageProducer->produceEmailNotificationMessage(
                    $message->getCompetitionId(),
                    $organizerEmail,
                    $emailSubject,
                    ['text' => $emailText]
                );
            }
            return;
        }

        // Publish FAILURE Message Email Notification to Organizer
        $organizerEmail = $competition->getCreatedBy()?->getEmail();
        $emailSubject = 'Notification: FAILED to Generate Winners for Competition ' . $competition->getId();
        $emailText = $competition->getTitle() . ' failed to Generate Winners...! <br> We are sorry for the inconvenience';
        if (!empty($organizerEmail)) {
            $this->messageProducer->produceEmailNotificationMessage(
                $message->getCompetitionId(),
                $organizerEmail,
                $emailSubject,
                ['text' => $emailText]
            );
        }
    }

    public function onStatusUpdateMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();
        if (!($message instanceof CompetitionUpdateStatusMessage)) {
            return;
        }

        /** @var Competition */
        $competition = $this->competitionRepository->find($message->getCompetitionId());

        if ($event->willRetry()) {
            // Publish RETRY Message Email Notification to Organizer
            $organizerEmail = $competition->getCreatedBy()?->getEmail();
            $emailSubject = 'Notification: RETRYING Competition Status Update for ' . $competition->getId();
            $emailText = 'RETRYING Status Update - ' . $competition->getId() . ' for status: ' . Competition::STATUSES[$competition->getStatus()];
            if (!empty($organizerEmail)) {
                $this->messageProducer->produceEmailNotificationMessage(
                    $message->getCompetitionId(),
                    $organizerEmail,
                    $emailSubject,
                    ['text' => $emailText]
                );
            }
            return;
        }


        // Publish FAILED Message Email Notification to Organizer
        $organizerEmail = $competition->getCreatedBy()?->getEmail();
        $emailSubject = 'Notification: FAILED Competition Status Update for ' . $competition->getId();
        $emailText = 'FAILED Status Update - ' . $competition->getId() . ' for status: ' . Competition::STATUSES[$competition->getStatus()];
        if (!empty($organizerEmail)) {
            $this->messageProducer->produceEmailNotificationMessage(
                $message->getCompetitionId(),
                $organizerEmail,
                $emailSubject,
                ['text' => $emailText]
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => [
                ['onSubmissionMessageFailed', 1],
                ['onWinnerTriggerMessageFailed', 2],
                ['onStatusUpdateMessageFailed', 3]
            ],
        ];
    }
}
