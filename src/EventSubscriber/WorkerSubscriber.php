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


// Since the most common Worker Errors will be Database errors, we should try not do any queries here.
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
        $this->dlqSender->send($queueName, $message, $event->getThrowable()->getMessage());

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
            ['text' => $emailText],
            2 // High Priority
        );
    }


    public function onWinnerTriggerMessageFailed(WorkerMessageFailedEvent $event): void
    {

        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();
        if (!($message instanceof WinnerTriggerMessage)) {
            return;
        }

        $organizerEmail = $message->getOrganizerEmail();
        if ($event->willRetry()) {
            // Publish RETRY Message Email Notification to Organizer
            $emailSubject = 'Notification: RETRYING to Generate Winners for Competition ' . $message->getCompetitionId();
            $emailText = $message->getCompetitionId() . ' is RETRYING to Generate Winners...! <br> We are sorry for the delay';
            if (!empty($organizerEmail)) {
                $this->messageProducer->produceEmailNotificationMessage(
                    $message->getCompetitionId(),
                    $organizerEmail,
                    $emailSubject,
                    ['text' => $emailText],
                    2 // High Priority
                );
            }
            return;
        }

        // Publish FAILURE Message Email Notification to Organizer
        $emailSubject = 'Notification: FAILED to Generate Winners for Competition ' . $message->getCompetitionId();
        $emailText = $message->getCompetitionId() . ' failed to Generate Winners...! <br> We are sorry for the inconvenience';
        if (!empty($organizerEmail)) {
            $this->messageProducer->produceEmailNotificationMessage(
                $message->getCompetitionId(),
                $organizerEmail,
                $emailSubject,
                ['text' => $emailText],
                2 // High Priority
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
        
        $organizerEmail = $message->getOrganizerEmail();
        if ($event->willRetry()) {
            // Publish RETRY Message Email Notification to Organizer
            $emailSubject = 'Notification: RETRYING Competition Status Update for ' . $message->getCompetitionId();
            $emailText = 'RETRYING Status Update - ' . $message->getCompetitionId() . ' for status: ' . Competition::STATUSES[$message->getTargetStatus()];
            if (!empty($organizerEmail)) {
                $this->messageProducer->produceEmailNotificationMessage(
                    $message->getCompetitionId(),
                    $organizerEmail,
                    $emailSubject,
                    ['text' => $emailText],
                    2 // High Priority
                );
            }
            return;
        }


        // Publish FAILED Message Email Notification to Organizer
        $emailSubject = 'Notification: FAILED Competition Status Update for ' . $message->getCompetitionId();
        $emailText = 'FAILED Status Update - ' . $message->getCompetitionId() . ' for status: ' . Competition::STATUSES[$message->getTargetStatus()];
        if (!empty($organizerEmail)) {
            $this->messageProducer->produceEmailNotificationMessage(
                $message->getCompetitionId(),
                $organizerEmail,
                $emailSubject,
                ['text' => $emailText],
                2 // High Priority
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
