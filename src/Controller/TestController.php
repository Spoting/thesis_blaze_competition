<?php

namespace App\Controller;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{

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
