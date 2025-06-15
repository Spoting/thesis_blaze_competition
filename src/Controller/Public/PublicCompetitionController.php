<?php

// src/Controller/Public/PublicCompetitionController.php
namespace App\Controller\Public;

use App\Constants\CompetitionConstants;
use App\Entity\Competition;
use App\Form\Public\SubmissionType;
use App\Message\CompetitionSubmittionMessage;
use App\Message\WinnerTriggerMessage;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

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
    public function handleSubmitForm(Competition $competition, Request $request): Response
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
            $competition_id = $competition->getId();
            $email = $formData['email'] ?? '';
            $phoneNumber = $formData['phoneNumber'] ?? '';
            $formFields = $formData;
            unset($formFields['email']);
            unset($formFields['phoneNumber']);

            // Check if this a new Submission ( from Redis )
            $submissionKey = $this->redisKeyBuilder->getCompetitionSubmissionKey($competition_id, $email, $phoneNumber);
            $newSubmission = false;
            if (!$this->redisManager->getValue($submissionKey)) {
                $now = new \DateTimeImmutable();
                $timeRemaining = $competition->getEndDate()->getTimestamp() - $now->getTimestamp();
                $this->redisManager->setValue($submissionKey, true, $timeRemaining);
                $newSubmission = true;
            }

            if ($newSubmission) {
                // Identify Priority
                $priorityKey = $this->identifyPriorityKey($competition);
                // Produce Message to RabbitMQ 
                $message = new CompetitionSubmittionMessage($formFields, $competition_id, $email, $phoneNumber);
                $this->messageBus->dispatch(
                    $message,
                    [new AmqpStamp(
                        CompetitionConstants::AMPQ_ROUTING['normal_submission'],
                        attributes: [
                            'priority' => $priorityKey,
                            'content_type' => 'application/json',
                            'content_encoding' => 'utf-8',
                        ]
                    )]
                );

                // Create the message object that will be dispatched.
                $message = new WinnerTriggerMessage($competition->getId());

                $amqpStamp = new AmqpStamp(
                    CompetitionConstants::AMPQ_ROUTING['winner_trigger'],
                    attributes: [
                        'headers' => ['x-delay' => 20000],
                        'content_type' => 'application/json',
                        'content_encoding' => 'utf-8',
                    ]
                );
                // Dispatch the message with the AmqpStamp to add the x-delay header.
                // The AmqpStamp constructor: new AmqpStamp(string $routingKey = null, int $priority = null, array $headers = [])
                $this->messageBus->dispatch(
                    $message,
                    [
                        $amqpStamp
                    ]
                );

                $count_key = $this->redisKeyBuilder->getCompetitionCountKey($competition_id);
                $total_count = $this->redisManager->incrementValue($count_key);

                $this->addFlash('success', 'Your submission has been received!' . $total_count);
            } else {
                $this->addFlash('error', 'Your submission is ALREADY been received! Chill...');
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

    // TODO: 
    private function identifyPriorityKey(Competition $competition)
    {
        $now = new \DateTimeImmutable();
        $endDate = $competition->getEndDate();
        $timeRemainingSeconds = $endDate->getTimestamp() - $now->getTimestamp();

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
