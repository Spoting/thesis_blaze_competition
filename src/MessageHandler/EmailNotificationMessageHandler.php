<?php

namespace App\MessageHandler;

use App\Message\EmailNotificationMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class EmailNotificationMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function __invoke(EmailNotificationMessage $message): void
    {
        dump('Attempting to sent Email ' . $message->getSubject());

        $email = (new TemplatedEmail())
            ->from('noreply@your-domain.com')
            ->to($message->getRecipientEmail())
            ->subject($message->getSubject())
            ->htmlTemplate('emails/' . $message->getTemplateId() . '.html.twig')
            ->context($message->getTemplateContext());

        $this->mailer->send($email);

        dump('Sent Email for ' . $message->getSubject());
    }
}