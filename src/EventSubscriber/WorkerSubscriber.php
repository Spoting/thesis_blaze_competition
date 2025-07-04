<?php

namespace App\EventSubscriber;

use App\Message\CompetitionSubmittionMessage;
use App\Messenger\DynamicCompetitionDlqSender;
use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpStamp;
use Jwage\PhpAmqpLibMessengerBundle\Transport\DsnParser;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;


class WorkerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DynamicCompetitionDlqSender $dlqSender
    ) {}

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {

        if ($event->willRetry()) {
            return; // Not yet time to dead-letter
        }

// phpamqplib://guest:guest@rabbitmq:5672/%2f
        // dump('kati');

        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();
        if (!($message instanceof CompetitionSubmittionMessage)) {
            return;
        }
        
        $queueName = 'dlq_competition_submission_' . $message->getCompetitionId();

        $this->dlqSender->send($queueName, $message);

        // dump('iswwws?');
        // $this->failureHandler->handle($event->getEnvelope(), $event->getThrowable());
    }


    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }
}