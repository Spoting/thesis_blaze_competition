<?php

namespace App\MessageHandler;

use App\Message\CompetitionSubmittionMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class CompetitionSubmittionMessageHandler
{
    public function __construct(    
    ) {}

    public function __invoke(CompetitionSubmittionMessage $message): void
    {
        dump('Attempting to insert Submission ' . $message->getCompetitionId() . " " . json_encode($message->getFormData()));

        // 1. GET
        // 2. INSERT
        // 2.1 on Error decrease Redis Counter & Remove Lock Key.
        // 3. Publish Email Notification to Public User
    }
}