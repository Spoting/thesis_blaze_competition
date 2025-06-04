<?php

// src/Controller/Public/PublicCompetitionController.php
namespace App\Controller\Public;

use App\Constants\CompetitionConstants;
use App\Entity\Competition;
use App\Form\Public\SubmissionType;
use App\Message\CompetitionSubmittionMessage;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class PublicCompetitionController extends AbstractController
{
    private MessageBusInterface $messageBus;
    private RedisManager $redisManager;

    public function __construct(
        MessageBusInterface $messageBus,
        RedisManager $redisManager
    ) {
        $this->messageBus = $messageBus;
        $this->redisManager = $redisManager;
    }

    #[Route('/', name: 'public_competitions', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // TODO: move functionality inside Repository
        $now = new \DateTimeImmutable();
        $competitions = $entityManager->getRepository(Competition::class)
            ->createQueryBuilder('c')
            // ->where('c.startDate <= :now')
            // ->andWhere('c.endDate > :now')
            // ->setParameter('now', $now)
            // ->orderBy('c.endDate', 'ASC')
            ->getQuery()
            ->getResult();

        // /** @var Competition $competition */
        // foreach ($competitions as $competition) {
        //     $competitionId = $competition->getId();
        //     $submissionCount = (int) $this->redisManager->getValue(CompetitionConstants::REDIS_PREFIX_COUNT_SUBMITTIONS . $competitionId);

        //     $competition->setTotalSubmissions($submissionCount);
        // }

        $response = $this->render('public/competitions.html.twig', [
            'competitions' => $competitions,
        ]);

        $response->setPublic();
        // $response->setMaxAge(30);

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
            $submissionKey = CompetitionConstants::REDIS_PREFIX_SUBMISSION_KEY . md5("$competition_id-$email-$phoneNumber");
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
                    [new AmqpStamp($priorityKey)]
                );

                $total_count = $this->redisManager->incrementValue(CompetitionConstants::REDIS_PREFIX_COUNT_SUBMITTIONS . $competition_id);

                // $this->addFlash('success', 'Your submission has been received!');
                $message = 'Your submission has been received! ' . $total_count;
            } else {
                // $this->addFlash('error', 'Your submission is ALREADY been received! Chill...');
                $message = 'Your submission is ALREADY been received! Chill...';
            }
            // return $this->redirectToRoute('public_competitions');
        }

        return $this->render('public/submit_form.html.twig', [
            'competition' => $competition,
            'form' => $form->createView(),
            'message' => $message,
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

    private function identifyPriorityKey(Competition $competition)
    {
        // TODO: Add Algorithm for determining priority
        $priorityKey = 'high';
        // $now = new \DateTimeImmutable();
        // $timeRemaining = $competition->getEndDate()->getTimestamp() - $now->getTimestamp();
        // if ($timeRemaining < (3600 * 24 * 7)) {
        //     $priorityKey = 'high';
        // } elseif ($timeRemaining < (3600 * 24 * 30)) {
        //     $priorityKey = 'medium';
        // } else {
        //     $priorityKey = 'normal';
        // }
        return $priorityKey;
    }
}
