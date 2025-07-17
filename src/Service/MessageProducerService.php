<?php

namespace App\Service;

use App\Message\CompetitionSubmittionMessage;
use App\Message\CompetitionUpdateStatusMessage;
use App\Message\EmailNotificationMessage;
use App\Message\VerificationEmailMessage;
use App\Message\WinnerTriggerMessage;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Jwage\PhpAmqpLibMessengerBundle\Transport\AmqpStamp as PushAmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;

class MessageProducerService
{

    public const AMPQ_ROUTING = [
        'low_priority_submission' => 'low_priority_submission',
        'high_priority_submission' => 'high_priority_submission',
        'winner_trigger' => 'winner_trigger',
        'competition_status' => 'competition_status',
        'email_verification' => 'email_verification',
        'email_notification' => 'email_notification',
    ];

    public function __construct(
        private MessageBusInterface $messageBus
    ) {}


    public function produceEmailVerificationMessage(string $verificationToken, string $receiverEmail, string $emailTokenExpirationString)
    {
        $message = new VerificationEmailMessage(
            $verificationToken,
            $receiverEmail,
            $emailTokenExpirationString,
        );
        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                self::AMPQ_ROUTING['email_verification'],
                attributes: [
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                ]
            )]
        );
    }

    public function produceEmailNotificationMessage(int $competitionId, string $receiverEmail, string $emailSubject, array $templateContext, int $maxPriority = 1)
    {
        $message = new EmailNotificationMessage(
            $competitionId,
            $receiverEmail,
            $emailSubject,
            templateContext: $templateContext
        );

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                self::AMPQ_ROUTING['email_notification'],
                attributes: [
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                    'priority' => $maxPriority,
                ]
            )]
        );
    }

    public function produceCompetitionStatusUpdateMessage(int $competitionId, int $delay_ms, string $target_status, string $organizerEmail)
    {
        $message_attributes = [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
        ];
        $message_attributes['headers'] = ['x-delay' => $delay_ms];

        $now = new \DateTime();

        $message = new CompetitionUpdateStatusMessage(
            $competitionId,
            $target_status,
            $now->format('Y-m-d H:i:s'),
            $delay_ms,
            $organizerEmail
        );

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                self::AMPQ_ROUTING['competition_status'],
                attributes: $message_attributes
            )]
        );
    }

    public function produceWinnerTriggerMessage(int $competitionId, int $delay_ms, string $organizerEmail)
    {
        $message_attributes = [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
        ];
        $message_attributes['headers'] = ['x-delay' => $delay_ms];

        $now = new \DateTime();

        $message = new WinnerTriggerMessage(
            $competitionId,
            $now->format('Y-m-d H:i:s'),
            $delay_ms,
            $organizerEmail
        );

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                self::AMPQ_ROUTING['winner_trigger'],
                attributes: $message_attributes
            )]
        );
    }

    public function produceSubmissionMessage(string $competitionEndTimestamp, array $submissionFormFields, int $competition_id, string $email)
    {
        $priorityKey = $this->identifyPriorityKey($competitionEndTimestamp);
        if ($priorityKey == 0) {
            $ampqStamp = new PushAmqpStamp(
                self::AMPQ_ROUTING['low_priority_submission'],
                attributes: [
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                ]
            );
        } else {
            $ampqStamp = new PushAmqpStamp(
                self::AMPQ_ROUTING['high_priority_submission'],
                attributes: [
                    'priority' => $priorityKey,
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                ]
            );
        }

        // Produce Message to RabbitMQ 
        $message = new CompetitionSubmittionMessage($submissionFormFields, $competition_id, $email);
        $this->messageBus->dispatch(
            $message,
            [$ampqStamp]
        );

    }

    public function identifyPriorityKey($competitionEndTimestamp)
    {
        $now = new \DateTimeImmutable();
        $timeRemainingSeconds = $competitionEndTimestamp - $now->getTimestamp();
        
        
        // Adjusted thresholds for demonstration
        if ($timeRemainingSeconds <= 10) { // Less than 10 seconds
            return 5;
        } elseif ($timeRemainingSeconds <= 20) { // Less than 20 seconds
            return 4;
        } elseif ($timeRemainingSeconds <= 30) { // Less than 30 seconds
            return 3;
        } elseif ($timeRemainingSeconds <= 60) { // Less than 1 minute
            return 2;
        } elseif ($timeRemainingSeconds <= 120) { // Less than 2 minutes
            return 1;
        } else {
            return 0; // 2 minutes and up
        }

        // if ($timeRemainingSeconds <= 28800) { // Less than 8 hour
        //     return 1;
        // } elseif ($timeRemainingSeconds <= 14400) { // Less than 4 hours
        //     return 2;
        // } elseif ($timeRemainingSeconds <= 9000) { // Less than 2.5 hours
        //     return 3;
        // } elseif ($timeRemainingSeconds <= 3600) { // Less than 1 Hours
        //     return 4;
        // } elseif ($timeRemainingSeconds <= 1800) { // Less then 30 Minutes
        //     return 5;
        // } else {
        //     return 0; // Inditcates that it will be routed to Low Priority Queue.
        // }
    }
}
