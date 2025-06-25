<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Constants\AppConstants;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use App\Form\Public\VerificationTokenType;
use App\Message\CompetitionSubmittionMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
// use Symfony\Contracts\Cache\CacheInterface;
// use Psr\Log\LoggerInterface;

class VerificationController extends AbstractController
{
    public function __construct(
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        // private LoggerInterface $logger
        private MessageBusInterface $messageBus,
    ) {}

    #[Route('/verify/{email}', name: 'app_verification_form')]
    public function showVerificationForm(Request $request, string $email): Response
    {
        $token = $request->query->get('token'); // Get token from URL query parameter

        // Create the form and pre-fill the 'token' field if it exists
        $formData = $token ? ['token' => $token] : [];

        $form = $this->createForm(VerificationTokenType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $submittedToken = $formData['token'];

            $verificationKey = $this->redisKeyBuilder->getVerificationTokenKey($submittedToken);
            $verificationData = $this->redisManager->getValue($verificationKey);

            // Throw Error if Verification Key doesnt exist.
            if (!$verificationData) {
                $this->addFlash('error', 'Invalid or expired verification token. Please re-enter or request a new one.');
                // $this->logger->warning(sprintf('Failed verification attempt for token: %s (not found in Redis).', $submittedToken));
                return $this->render('public/verification_form.html.twig', [
                    'form' => $form,
                    'email' => $email,
                ]);
            }

            $verificationData = json_decode($verificationData, true);
            // Confirm that Email matches
            if ($email && $verificationData['email'] !== $email) {
                $this->addFlash('error', 'The token does not match the provided email.');
                //  $this->logger->warning(sprintf('Token %s mismatch for email %s vs Redis email %s', $submittedToken, $email, $verificationData['email']));
                return $this->render('public/verification_form.html.twig', ['form' => $form, 'email' => $email]);
            }

            // Remove the verification token from Redis
            $this->redisManager->deleteKey($verificationKey);

            // Fetch Stored Data from Submission Key
            $submissionKey = $this->redisKeyBuilder->getCompetitionSubmissionKey($verificationData['competition_id'], $verificationData['email']);
            $submissionKeyData = $this->redisManager->getValue($submissionKey);
            $submissionKeyData = json_decode($submissionKeyData, true);
            $competition_id = $submissionKeyData['competition_id'];
            // $status = $submissionKeyData['status'];
            $submissionFormFields = $submissionKeyData['formData'];
            $competition_ended_at = $submissionKeyData['competition_ended_at'];


            // Update the submission key and ttl, as it's now verified
            // Keep lightweight Data.
            $newSubmissionKeyData = [
                'status' => 'verified',
            ];
            $competitionTimeRemaining = $competition_ended_at - new \DateTimeImmutable()->getTimestamp();

            $this->redisManager->setValue($submissionKey, json_encode($newSubmissionKeyData), $competitionTimeRemaining);
            $this->addFlash('success', 'Email successfully verified!');


            // Identify Priority of Message
            $priorityKey = $this->identifyPriorityKey($competition_ended_at);

            // TODO: 

            // Produce Message to RabbitMQ 
            $message = new CompetitionSubmittionMessage($submissionFormFields, $competition_id, $email);
            $this->messageBus->dispatch(
                $message,
                [new AmqpStamp(
                    AppConstants::AMPQ_ROUTING['high_priority_submission'],
                    attributes: [
                        'priority' => $priorityKey,
                        'content_type' => 'application/json',
                        'content_encoding' => 'utf-8',
                    ]
                )]
            );

            // Increment the Total Count for this Competition
            $count_key = $this->redisKeyBuilder->getCompetitionCountKey($competition_id);
            $total_count = $this->redisManager->incrementValue($count_key);

            $this->addFlash('success', 'TOTAL ENTRIES: ' . $total_count);

            // Show success page
            return $this->redirectToRoute('app_submission_success');
        }

        return $this->render('public/verification_form.html.twig', [
            'form' => $form,
            'email' => $email,
        ]);
    }

    // TODO:
    // You'll also need a 'resend token' action, likely on this same page
    // app_resend_verification_email (implement similar logic to initial submission, but only for existing, unverified entries)

    #[Route('/submission-success', name: 'app_submission_success')]
    public function submissionSuccess(): Response
    {
        return $this->render('public/submission_success.html.twig');
    }


    private function identifyPriorityKey($competitionEndTimestamp)
    {
        return 9;

        $now = new \DateTimeImmutable();
        $timeRemainingSeconds = $competitionEndTimestamp - $now->getTimestamp();

        // Map time remaining to a 0-10 priority scale (adjust values and tiers as needed)
        // Ensure this aligns with the 'x-max-priority' set in messenger.yaml
        if ($timeRemainingSeconds <= 3600) { // Less than 1 hour
            return 10;
        } elseif ($timeRemainingSeconds <= 21600) { // Less than 6 hours
            return 8;
        } elseif ($timeRemainingSeconds <= 86400) { // Less than 1 day
            return 5;
        } elseif ($timeRemainingSeconds <= 259200) { // Less than 3 days
            return 3;
        } else {
            return 1; // Default low priority for competitions far in the future
        }
    }
}
