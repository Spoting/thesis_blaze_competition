<?php

namespace App\Controller;

use App\Entity\Competition;
use App\Message\CompetitionSubmittionMessage;
use App\Message\CompetitionUpdateStatusMessage;
use App\Message\EmailNotificationMessage;
use App\Message\VerificationEmailMessage;
use App\Message\WinnerTriggerMessage;
use App\Service\MessageProducerService;
use App\Service\RedisManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Factory\UuidFactory;

class TestController extends AbstractController
{


    public function __construct(
        private UuidFactory $uuidFactory,
        private RedisManager $redis,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/test-notification-email', name: 'test_emails')]
    public function testMessages(): Response
    {
        $status = "";

        // Email Verification
        $message = $this->generateVerificationEmailMessage();
        $status .= "Verification: OK\n";

        // Email Notification User Failure
        $message = $this->generateEmailNotificationMessage('Submission Failure', 'Your Submission has Failed to be Processed .');
        $status .= "Notify Submission Failure: OK\n";

        // Email Notification User Sucess
        $message = $this->generateEmailNotificationMessage('Submission Success', 'Your Submission has been Succesfully Processed.');
        $status .= "Notify Submission Success: OK\n";

        // Email Organizer Start Competition
        $message = $this->generateEmailNotificationMessage('Started Competition', 'Competition has Started.');
        $status .= "Notify Organizer Start: OK\n";


        // Email Organizer End Competition
        $message = $this->generateEmailNotificationMessage('Ended Competition', 'Competition has Ended.');
        $status .= "Notify Organizer End: OK\n";

        // Email Organizer Winner(s) Generated Competition
        $message = $this->generateEmailNotificationMessage('Winners Generated Competition', 'Competition has new Winners!.');
        $status .= "Notify Organizer Winner(s) Generated: OK\n";

        return new Response($status);
    }

    #[Route('/test', name: 'just_a_test')]
    public function test(): Response
    {
        $competitionManager = $this->entityManager->getRepository(Competition::class);
        $competition = $competitionManager->find(321);
        $this->entityManager->flush();

        return new Response('OK');
    }

    #[Route('/test-submissions/{total}/{prefix}/{priority?}', name: 'test_submissions')]
    public function testSubmissions(int $total, int $prefix, ?int $priority): Response
    {
        $status = [];
        for ($i = 0; $i < $total; $i++) {
            $this->generateCompetitionSubmissionMessage($i . '' . $prefix, $status, $priority);
        }

        // return new Response(json_encode($status, JSON_PRETTY_PRINT));
        return new Response('OK');
    }

    #[Route('/test-comp-status-updates', name: 'test_comp_status_updates')]
    public function testCompStatusUpdates(): Response
    {
        $status = [
            'running',
            'submissions_ended',
        ];

        foreach ($status as $target_status) {
            $this->generateCompetitionStatusUpdateMessage($target_status);
        }

        $this->generateWinnerTriggerMessage();

        return new Response('OK');
    }

    private function generateVerificationEmailMessage()
    {
        $receiverEmail = 'kati@ekane.pin';
        $verificationToken = $this->uuidFactory->create()->toRfc4122();
        $expiring_at = new \DateTime('+3 minutes');

        $message = new VerificationEmailMessage($verificationToken, $receiverEmail, $expiring_at->format('Y-m-d H:i:s'));

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                MessageProducerService::AMPQ_ROUTING['email_verification'],
                attributes: [
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                ]
            )]
        );
        return $message;
    }

    private function generateEmailNotificationMessage($case, $text)
    {
        $receiverEmail = 'notify@ekane.pin';

        $competition_id = '10';
        // $redis->setValue('verification_token_'. $verificationToken, json_encode(['email' => $receiverEmail, 'otherdata' => 'skata'], 1749990508));

        $message = new EmailNotificationMessage(
            $competition_id,
            $receiverEmail,
            $case . ' - Test - Notification Email',
            templateContext: ['text' => $text, 'competition_id' => $competition_id]
        );

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                MessageProducerService::AMPQ_ROUTING['email_notification'],
                attributes: [
                    'content_type' => 'application/json',
                    'content_encoding' => 'utf-8',
                ]
            )]
        );
    }

    private function generateCompetitionSubmissionMessage($i, &$mock, $priority = null)
    {
        $message_attributes = [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
        ];
        if ($priority == null) {
            $priorityKey = rand(0, 5); 
        } else {
            $priorityKey = $priority;
        }

        if ($priorityKey == 0) {
            $queue = MessageProducerService::AMPQ_ROUTING['low_priority_submission'];
            $mock['low_priority'][$i] = $priorityKey;
        } else {
            $message_attributes['priority'] = $priorityKey;
            $queue = MessageProducerService::AMPQ_ROUTING['high_priority_submission'];
            $mock['high_priority'][$i] = $priorityKey;
        }

        $message = new CompetitionSubmittionMessage(
            ['email' => 'kati@kati.com', 'priority' => $i . "|" . $priorityKey],
            321,
            'kati'.$i.'@kati.com'.$i
        );

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                $queue,
                attributes: $message_attributes
            )]
        );

        return $priorityKey;
    }

    private function generateCompetitionStatusUpdateMessage($target_status)
    {
        $message_attributes = [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
        ];

        if ($target_status == 'submissions_ended') {
            $x_delay = 10000;
        } elseif ($target_status == 'running') {
            $x_delay = 1000;
        }
        $message_attributes['headers'] = ['x-delay' => $x_delay];

        $now = new \DateTime();
        $message = new CompetitionUpdateStatusMessage(
            322,
            $target_status,
            $now->format('Y-m-d H:i:s'),
            $x_delay
        );

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                MessageProducerService::AMPQ_ROUTING['competition_status'],
                attributes: $message_attributes
            )]
        );
    }

    private function generateWinnerTriggerMessage()
    {
        $message_attributes = [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
        ];
        $x_delay = 12000;
        $message_attributes['headers'] = ['x-delay' => $x_delay];

        $now = new \DateTime();
        $message = new WinnerTriggerMessage(
            322,
            $now->format('Y-m-d H:i:s'),
            $x_delay
        );

        $this->messageBus->dispatch(
            $message,
            [new AmqpStamp(
                MessageProducerService::AMPQ_ROUTING['winner_trigger'],
                attributes: $message_attributes
            )]
        );
    }

    #[Route('/test', name: 'test')]
    public function sendTemplatedEmail(): Response
    {
        phpinfo();
        return new Response('OK');
        // return $this->render('mailer/email_status.html.twig', [
        //     'status' => 'Templated email send attempt complete.',
        // ]);
    }
}
