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

class PublicCompetitionController extends AbstractController
{
    private MessageBusInterface $messageBus;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    #[Route('/', name: 'public_competitions', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager,  Request $request): Response
    {
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

        // Determine the latest end date of the displayed competitions
        $lastModified = null;
        if (!empty($competitions)) {
            $latestEndDate = null;
            foreach ($competitions as $competition) {
                if ($latestEndDate === null || $competition->getEndDate() > $latestEndDate) {
                    $latestEndDate = $competition->getEndDate();
                }
            }
            // Use the latest end date of an *active* competition
            // Or, consider the latest modification date of *any* competition if that's more relevant
            $lastModified = $latestEndDate; // Or get a general last modified timestamp from your competition data
        }

        // Set Cache-Control headers
        $response->setPublic(); // Indicates the response can be cached by shared caches (proxies)
        $response->setMaxAge(600); // Cache for 10 minutes (600 seconds)
        $response->setSharedMaxAge(3600); // Shared caches can cache for 1 hour

        // Set Last-Modified header
        if ($lastModified) {
            $response->setLastModified($lastModified);
        }

        // Handle ETag (optional, but good for robust caching)
        // An ETag should change whenever the content changes.
        // For a list of competitions, a hash of the competition IDs and their end dates could work.
        $etag = md5(json_encode(array_map(fn($c) => ['id' => $c->getId(), 'endDate' => $c->getEndDate()->getTimestamp()], $competitions)));
        $response->setEtag($etag);

        // Check if the client's cache is still fresh
        if ($response->isNotModified($request)) {
            // Return 304 Not Modified response
            return $response;
        }

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

        $priorityKey = $this->identifyPriorityKey($competition);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            $competition_id = $competition->getId(); // Use the ID from the fetched entity
            $email = $formData['email'] ?? '';
            $phoneNumber = $formData['phoneNumber'] ?? '';
            $formFields = $formData;
            unset($formFields['email']);
            unset($formFields['phoneNumber']);

            $message = new SubmitCompetitionEntryMessage($formData, $competition_id, $email, $phoneNumber);
            $this->messageBus->dispatch(
                $message,
                [new AmqpStamp($priorityKey)]
            );

            $this->addFlash('success', 'Your submission has been received!');
            return $this->redirectToRoute('public_competitions');
        }

        return $this->render('public/submit_form.html.twig', [
            'competition' => $competition,
            'form' => $form->createView(),
        ]);
    }

    private function validateCompetition(Competition $competition) {
        if (
            !$competition instanceof Competition
            || $competition->getEndDate() < new \DateTimeImmutable()
        ) {
            throw $this->createNotFoundException('Competition not found or has ended.');
        }
    }

    private function identifyPriorityKey(Competition $competition) {
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
