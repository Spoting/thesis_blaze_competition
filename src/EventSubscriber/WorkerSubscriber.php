<?php

namespace App\EventSubscriber;

use App\Message\CompetitionSubmittionMessage;
use App\Messenger\DynamicCompetitionDlqSender;
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
    ) {}

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
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
        $emailSubject = 'Failed Submission';
        $emailText = 'We are sorry, there is been a Server Error. Your Submission Failed for Competition: ' . $message->getCompetitionId();
        $this->messageProducer->produceEmailNotificationMessage(
            $message->getCompetitionId(),
            $message->getEmail(),
            $emailSubject,
            ['text' => $emailText]
        );
    }


    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }
}
