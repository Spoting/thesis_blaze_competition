<?php

namespace App\MessageHandler;

use App\Message\SendVerificationEmailMessage;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
class SendVerificationEmailMessageHandler
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $router,
    ) {}

    public function __invoke(SendVerificationEmailMessage $message): void
    {
        dump('Attempting to sent Email ' . $message->getEmail());

        $verificationUrl = $this->router->generate(
            'app_verification_form', // Target route is the main verification form
            [
                'email' => $message->getEmail(), // This fills the {email} path parameter
                'token' => $message->getVerificationToken(), // This becomes a ?token= query parameter
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from('noreply@your-domain.com')
            ->to($message->getEmail())
            ->subject('Please Verify Your Submission')
            ->htmlTemplate('emails/verification_email.html.twig')
            ->context([
                'verification_url' => $verificationUrl,
                'token' => $message->getVerificationToken(),
                'expiration_time' => $message->getExpiration(),
            ]);

        $this->mailer->send($email);

        dump('Sent Email for ' . $message->getEmail());
    }
}


// <?php

// namespace App\MessageHandler;

// use App\Message\SendVerificationEmailMessage;
// use Symfony\Bridge\Twig\Mime\TemplatedEmail;
// use Symfony\Component\Mailer\MailerInterface;
// use Symfony\Component\Messenger\Attribute\AsMessageHandler;
// use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// use Psr\Log\LoggerInterface;

// #[AsMessageHandler]
// class SendVerificationEmailMessageHandler
// {
//     public function __construct(
//         private MailerInterface $mailer,
//         private UrlGeneratorInterface $router,
//         private LoggerInterface $logger
//     ) {}

//     public function __invoke(SendVerificationEmailMessage $message): void
//     {
//         try {
//             $verificationUrl = $this->router->generate(
//                 'app_verify_email', // This must be the route name of your verification endpoint
//                 ['token' => $message->getVerificationToken()],
//                 UrlGeneratorInterface::ABSOLUTE_URL
//             );

//             $email = (new TemplatedEmail())
//                 ->from('noreply@your-domain.com') // Ensure this sender email is configured in your Mailer DSN
//                 ->to($message->getEmail())
//                 ->subject('Please Verify Your Submission')
//                 ->htmlTemplate('emails/verification_email.html.twig') // A dedicated template
//                 ->context([
//                     'verificationUrl' => $verificationUrl,
//                     'expiration_time' => $message->getExpiration(),
//                     'formData' => $message->getFormData(),
//                 ]);

//             $this->mailer->send($email);
//             $this->logger->info(sprintf('Verification email sent to %s for token %s.', $message->getEmail(), $message->getVerificationToken()));
//         } catch (\Exception $e) {
//             $this->logger->error(sprintf('Failed to send verification email to %s for token %s: %s', $message->getEmail(), $message->getVerificationToken(), $e->getMessage()));
//         }
//     }
// }