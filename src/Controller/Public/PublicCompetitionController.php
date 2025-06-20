<?php

namespace App\Controller\Public;

use App\Constants\CompetitionConstants;
use App\Entity\Competition;
use App\Form\Public\SubmissionType;
use App\Message\VerificationEmailMessage;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Factory\UuidFactory;
// use Symfony\Contracts\Cache\TagAwareCacheInterface;

class PublicCompetitionController extends AbstractController
{
    private MessageBusInterface $messageBus;
    private RedisManager $redisManager;
    private RedisKeyBuilder $redisKeyBuilder;

    public function __construct(
        MessageBusInterface $messageBus,
        RedisManager $redisManager,
        RedisKeyBuilder $redisKeyBuilder,
    ) {
        $this->messageBus = $messageBus;
        $this->redisManager = $redisManager;
        $this->redisKeyBuilder = $redisKeyBuilder;
    }

    #[Route('/', name: 'public_competitions', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // cache:pool:clear
        // $resultCache = $entityManager->getConfiguration()->getResultCache();
        // $resultCache->deleteItem('competition_list_current');

        // TODO: move functionality inside Repository
        $now = new \DateTimeImmutable();
        $competitions = $entityManager->getRepository(Competition::class)
            ->createQueryBuilder('c')
            // ->where('c.startDate <= :now')
            // ->andWhere('c.endDate > :now')
            // ->setParameter('now', $now)
            ->orderBy('c.endDate', 'ASC')
            // ->setMaxResults(1)
            ->getQuery()
            // ->enableResultCache()
            // ->setResultCacheId('competition_list_current')
            ->getResult();

        $response = $this->render('public/competitions.html.twig', [
            'competitions' => $competitions,
        ]);

        // $response->setPublic();
        // $response->setMaxAge(3600);
        // $response->setSharedMaxAge(3600);

        return $response;
    }

    #[Route('/competition/{id}/submit', name: 'public_competition_submit', methods: ['GET', 'POST'])]
    public function handleSubmitForm(Competition $competition, Request $request, UuidFactory $uuidFactory): Response
    {
        $this->validateCompetition($competition);

        $form = $this->createForm(SubmissionType::class, null, [
            'competition_id' => $competition->getId(),
            'form_fields' => $competition->getFormFields(),
        ]);

        $form->handleRequest($request);

        $message = '';
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            // Extract Data
            $competitionId = $competition->getId();
            $receiverEmail = $formData['email'] ?? '';
            $formFields = $formData;
            unset($formFields['email']);

            // Check if this a new Submission ( from Redis )
            $submissionKey = $this->redisKeyBuilder->getCompetitionSubmissionKey($competitionId, $receiverEmail);
            $submissionKeyData = $this->redisManager->getValue($submissionKey);
            $submissionKeyData = json_decode($submissionKeyData, true);
            // User Already Submitted form. Either he is pending email approval or already confirmed.
            if (!empty($submissionKeyData)) {
                if ($submissionKeyData['status'] == RedisKeyBuilder::VERIFICATION_PENDING_VALUE) {
                    $this->addFlash('warning', 'You have recently attempted to submit for this competition. Please check your email for a verification link.');
                } else {
                    $this->addFlash('warning', 'Your submission is ALREADY been received and verified! Chill while we process it...');
                }
                return $this->render('public/submit_form.html.twig', [
                    'competition' => $competition,
                    'form' => $form->createView(),
                ]);
            }

            // return $this->redirectToRoute('public_competition_submit');
            try {
                // Set the initial submission key with a short TTL (for pending verification)
                $newSubmissionKeyData = [
                    'competition_id' => $competitionId,
                    'status' => 'pending_verification',
                    'formData' => $formData, // Store all form data for later processing
                    'competition_ended_at' => $competition->getEndDate()->getTimestamp(),
                ];
                $this->redisManager->setValue($submissionKey, json_encode($newSubmissionKeyData), RedisKeyBuilder::VERIFICATION_TOKEN_TTL_SECONDS);

                // Generate Verification Token.
                $verificationToken = $uuidFactory->create()->toRfc4122();
                $emailTokenExpirationDateTime = (new \DateTimeImmutable())
                    ->modify('+' . RedisKeyBuilder::VERIFICATION_TOKEN_TTL_SECONDS . ' seconds');

                //  Store a new key Token to Redis
                $verificationData = [
                    'email' => $receiverEmail,
                    'competition_id' => $competitionId,
                    // 'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                    'email_token_expires_at' => $emailTokenExpirationDateTime->getTimestamp(),
                    // 'competition_ends_at' => $competition->getEndDate()->getTimestamp(),
                ];
                $verificationKey = $this->redisKeyBuilder->getVerificationTokenKey($verificationToken);
                $this->redisManager->setValue($verificationKey, json_encode($verificationData), RedisKeyBuilder::VERIFICATION_TOKEN_TTL_SECONDS);


                
                // Send the email and token to the verification_email queue.
                $emailTokenExpirationString = $emailTokenExpirationDateTime->format('Y-m-d H:i:s');
                $message = new VerificationEmailMessage(
                    $verificationToken,
                    $receiverEmail,
                    $emailTokenExpirationString,
                );
                $this->messageBus->dispatch(
                    $message,
                    [new AmqpStamp(
                        CompetitionConstants::AMPQ_ROUTING['email_verification'],
                        attributes: [
                            'content_type' => 'application/json',
                            'content_encoding' => 'utf-8',
                        ]
                    )]
                );

                $this->addFlash('success', 'A verification email has been sent to your email address. Please check your inbox and spam folder.');
                return $this->redirectToRoute('app_verification_form', ['email' => $receiverEmail]);

            } catch (\Exception $e) {
                // $this->logger->error(sprintf('Error during initial form submission for email %s: %s', $receiverEmail, $e->getMessage()));
                $this->addFlash('error', 'An error occurred during submission. Please try again. ' . $e->getMessage());
                // If Error happened remove Keys from Redis
                $this->redisManager->deleteKey($submissionKey);
                $this->redisManager->deleteKey($verificationKey);
            }

            // return $this->redirectToRoute('public_competitions');
        }

        return $this->render('public/submit_form.html.twig', [
            'competition' => $competition,
            'form' => $form->createView(),
        ]);
    }

    private function validateCompetition(Competition $competition)
    {
        if (
            !$competition instanceof Competition
            || $competition->getEndDate() < new \DateTimeImmutable()
        ) {
            throw $this->createNotFoundException('Competition not found or has ended.');
        }
    }
}
