<?php

namespace App\Controller;

use App\Constants\CompetitionConstants;
use App\Message\SendVerificationEmailMessage;
use App\Service\RedisManager;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Factory\UuidFactory;

class TestController extends AbstractController
{



    #[Route('/test-mail-queue', name: 'test_mail_queue')]
    public function testMailQueue(MessageBusInterface $messageBus, UuidFactory $uuidFactory, RedisManager $redis): Response
    {

        $receiverEmail = 'kati@ekane.pin';
        $verificationToken = $uuidFactory->create()->toRfc4122();
        $competition_expirations_at = new \DateTime('+10 days');
        
        
        $expiring_at = new \DateTime('+3 minutes');
        
        $redis->setValue('verification_token_'. $verificationToken, json_encode(['email' => $receiverEmail, 'otherdata' => 'skata'], 1749990508));

        $message = new SendVerificationEmailMessage($verificationToken, $receiverEmail, $expiring_at->format('Y-m-d H:i:s'));
        $messageBus->dispatch(
            $message,
            [new AmqpStamp(
                CompetitionConstants::AMPQ_ROUTING['email_verification'],
                attributes: [
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                ]
            )]
        );

        
        
        return new Response('Added to Queue & Redis Key set!');
        // return $this->render('mailer/email_status.html.twig', [
        //     'status' => 'Templated email send attempt complete.',
        // ]);
    }

    #[Route('/send-templated-email', name: 'app_send_templated_email')]
    public function sendTemplatedEmail(MailerInterface $mailer): Response
    {
        $expiring_at = new \DateTime('+3 minutes');

        $email = (new TemplatedEmail())
            ->from('noreply@your-domain.com')
            ->to('john.doe@example.com')
            ->subject('Welcome to Our Service!')
            ->htmlTemplate('emails/test.html.twig') // Specify the Twig template
            ->context([ // Pass variables to the Twig template
                'expiration_time' => $expiring_at->format('Y-m-d H:i:s'),
            ]);

        // try {
        $mailer->send($email);
        // $this->addFlash('success', 'Templated email sent successfully!');
        // } catch (\Exception $e) {
        //     $this->addFlash('error', 'Error sending templated email: ' . $e->getMessage());
        // }

        return new Response('OK');
        // return $this->render('mailer/email_status.html.twig', [
        //     'status' => 'Templated email send attempt complete.',
        // ]);
    }
}
