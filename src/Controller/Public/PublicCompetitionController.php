<?php

// src/Controller/Public/PublicCompetitionController.php
namespace App\Controller\Public;

use App\Entity\Competition;
use App\Form\Public\SubmissionType;
use App\Message\SubmitCompetitionEntryMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PublicCompetitionController extends AbstractController
{
    private MessageBusInterface $messageBus;
    private CacheInterface $cache;

    public function __construct(
        MessageBusInterface $messageBus,
        CacheInterface $cache
    ) {
        $this->messageBus = $messageBus;
        $this->cache = $cache;
    }

    #[Route('/', name: 'public_competitions', methods: ['GET'], stateless: true)]
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
        $response = $this->render('public/competitions.html.twig', [
            'competitions' => $competitions,
        ]);

        $response->setPublic();
        // $response->setMaxAge(30);

        return $response;
    }

    #[Route('/competition/{id}/submit', name: 'public_competition_submit_show', methods: ['GET'])]
    public function displaySubmitForm(Competition $competition): Response
    {
        $this->validateCompetition($competition);

        $form = $this->createForm(SubmissionType::class, null, [
            'competition_id' => $competition->getId(), // Use the ID from the fetched entity
            'form_fields' => $competition->getFormFields(),
        ]);

        return $this->render('public/submit_form.html.twig', [
            'competition' => $competition,
            'form' => $form->createView(),
            'message' => '',
        ]);
    }

    #[Route('/competition/{id}/submit', name: 'public_competition_submit_handle', methods: ['POST'])]
    // Same here, the Competition object is automatically available
    public function handleSubmitForm(Competition $competition, Request $request): Response
    {
        $this->validateCompetition($competition);

        $form = $this->createForm(SubmissionType::class, null, [
            'competition_id' => $competition->getId(), // Use the ID from the fetched entity
            'form_fields' => $competition->getFormFields(),
        ]);

        $form->handleRequest($request);

        
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            // Extract Data
            $competition_id = $competition->getId();
            $email = $formData['email'] ?? '';
            $phoneNumber = $formData['phoneNumber'] ?? '';
            $formFields = $formData;
            unset($formFields['email']);
            unset($formFields['phoneNumber']);

            // Check if this a new Submission ( from Cache Redis )
            $newSubmission = false;
            $submissionKey = "submission_key_" . md5("$competition_id-$email-$phoneNumber");
            $this->cache->get($submissionKey, function (ItemInterface $item) use ($competition, &$newSubmission): void {

                // Calculate when to Expire this key. This is used to avoid multiple Submissions by the same person.                
                $now = new \DateTimeImmutable();
                $timeRemaining = $competition->getEndDate()->getTimestamp() - $now->getTimestamp();
                // Cache until the competition ends
                $item->expiresAfter($timeRemaining)->set(true);
                // $item->tag($competition->getId()); // Throws Error : comes from a non tag-aware pool: you cannot tag it.
                $newSubmission = true;
            });

            if ($newSubmission) {
                // Identify Priority
                $priorityKey = $this->identifyPriorityKey($competition);
                // Produce Message to RabbitMQ 
                $message = new SubmitCompetitionEntryMessage($formData, $competition_id, $email, $phoneNumber);
                $this->messageBus->dispatch(
                    $message,
                    [new AmqpStamp($priorityKey)]
                );

                // $this->addFlash('success', 'Your submission has been received!');
                $message = 'Your submission has been received!';
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
