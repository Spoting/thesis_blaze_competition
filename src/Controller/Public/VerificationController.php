<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use App\Form\Public\VerificationTokenType;
use App\Service\MessageProducerService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Annotation\Route;
// use Symfony\Contracts\Cache\CacheInterface;
// use Psr\Log\LoggerInterface;

class VerificationController extends AbstractController
{
    public function __construct(
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        // private LoggerInterface $logger
        private MessageProducerService $messageProducerService,
    ) {}

    #[Route('/verify', name: 'app_verification_form')]
    #[Cache(smaxage: 3600, public: true)]
    public function showVerificationForm(Request $request): Response
    {
        $message = null;

        $identifier = $request->query->get('identifier');
        $token = $request->query->get('token'); // Get token from URL query parameter

        // Create the form and pre-fill the 'token' field if it exists
        $formData = $token ? ['token' => $token] : [];

        $form = $this->createForm(VerificationTokenType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $submittedToken = $formData['token'];

            $verificationKey = $this->redisKeyBuilder->getVerificationTokenKey($identifier);
            $verificationData = $this->redisManager->getValue($verificationKey);

            // Throw Error if Verification Key doesnt exist.
            if (!$verificationData) {
                // $this->addFlash('error', 'Invalid or expired verification token. Please re-enter or request a new one.');
                // $this->logger->warning(sprintf('Failed verification attempt for token: %s (not found in Redis).', $submittedToken));
                $message = [
                    'type' => 'error',
                    'text' => 'Invalid or expired verification token. Please re-enter or request a new one.'
                ];
                return $this->render('public/verification_form.html.twig', [
                    'form' => $form,
                    'identifier' => $identifier,
                    'message' => $message,
                ]);
            }

            $verificationData = json_decode($verificationData, true);
            $email = $verificationData['email'];

            // Confirm that Token matches
            if ($submittedToken && $verificationData['verification_token'] !== $submittedToken) {
                // $this->addFlash('error', 'The token does not match the provided email.');
                //  $this->logger->warning(sprintf('Token %s mismatch for email %s vs Redis email %s', $submittedToken, $email, $verificationData['email']));
                $message = [
                    'type' => 'error',
                    'text' => 'The provided token is incorrect. Please check and try again.'
                ];

                return $this->render('public/verification_form.html.twig', [
                    'form' => $form->createView(),
                    'identifier' => $identifier,
                    'message' => $message,
                ]);
            }

            // Remove the verification token from Redis
            $this->redisManager->deleteKey($verificationKey);

            // Fetch Stored Data from Submission Key
            $submissionKey = $this->redisKeyBuilder->getCompetitionSubmissionKey($verificationData['competition_id'], $verificationData['email']);
            $submissionKeyData = $this->redisManager->getValue($submissionKey);
            $submissionKeyData = json_decode($submissionKeyData, true);
            $competitionId = $submissionKeyData['competition_id'];
            $submissionFormFields = $submissionKeyData['formData'];
            $competitionEndedAt = $submissionKeyData['competition_ended_at'];


            // Update the submission key and ttl, as it's now verified
            // Keep lightweight Data.
            $newSubmissionKeyData = [
                'status' => 'verified',
            ];
            $now = new \DateTimeImmutable();
            $competitionTimeRemaining = $competitionEndedAt - $now->getTimestamp();

            $this->redisManager->setValue($submissionKey, json_encode($newSubmissionKeyData), $competitionTimeRemaining);
            // $this->addFlash('success', 'Email successfully verified!');

            // Publish Message
            $this->messageProducerService->produceSubmissionMessage(
                $competitionEndedAt,
                $submissionFormFields,
                $competitionId,
                $email
            );

            // Increment the Total Count for this Competition
            $count_key = $this->redisKeyBuilder->getCompetitionCountKey($competitionId);
            $total_count = $this->redisManager->incrementValue($count_key);

            // $this->addFlash('success', 'TOTAL ENTRIES: ' . $total_count);

            // Show success page
            return $this->redirectToRoute('app_submission_success');
        }

        return $this->render('public/verification_form.html.twig', [
            'form' => $form,
            'identifier' => $identifier,
            'message' => $message
        ]);
    }

    // TODO:
    // You'll also need a 'resend token' action, likely on this same page
    // app_resend_verification_email (implement similar logic to initial submission, but only for existing, unverified entries)

    #[Route('/submission-success', name: 'app_submission_success')]
    #[Cache(smaxage: 3600, public: true)]
    public function submissionSuccess(): Response
    {
        return $this->render('public/submission_success.html.twig');
    }
}
