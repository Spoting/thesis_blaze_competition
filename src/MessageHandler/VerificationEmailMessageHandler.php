<?php

namespace App\MessageHandler;

use App\Message\VerificationEmailMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class VerificationEmailMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $router,
    ) {}

    public function __invoke(VerificationEmailMessage $message): void
    {
        dump('Attempting to sent Email ' . $message->getRecipientEmail());

        $verificationUrl = $this->router->generate(
            'app_verification_form', 
            [
                'identifier' => $message->getIdentifier(),
                'token' => $message->getVerificationToken(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from('noreply@your-domain.com')
            ->to($message->getRecipientEmail())
            ->subject('Please Verify Your Submission')
            ->htmlTemplate('emails/verification_email.html.twig')
            ->context([
                'verification_url' => $verificationUrl,
                'token' => $message->getVerificationToken(),
                'expiration_time' => $message->getExpiration(),
            ]);

        $this->mailer->send($email);

        dump('Sent Email for ' . $message->getRecipientEmail());
    }
}
