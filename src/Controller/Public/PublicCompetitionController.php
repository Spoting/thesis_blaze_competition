<?php

// src/Controller/Public/PublicCompetitionController.php
namespace App\Controller\Public;

use App\Entity\Competition;
use App\Form\Public\SubmissionType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicCompetitionController extends AbstractController
{
    #[Route('/', name: 'public_competitions')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $now = new \DateTimeImmutable();
        $competitions = $entityManager->getRepository(Competition::class)
            ->createQueryBuilder('c')
            ->where('c.startDate <= :now')
            ->andWhere('c.endDate > :now')
            ->setParameter('now', $now)
            ->orderBy('c.endDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('public/competitions.html.twig', [
            'competitions' => $competitions,
        ]);
    }



    #[Route('/competition/{id}/submit', name: 'public_submit_form')]
    public function submitForm(int $id, EntityManagerInterface $entityManager, Request $request): Response
    {
        $competition = $entityManager->getRepository(Competition::class)->find($id);

        if (
            !$competition instanceof Competition
            || $competition->getEndDate() < new \DateTimeImmutable()
        ) {
            throw $this->createNotFoundException('Competition not found or has ended.');
        }

        $form = $this->createForm(SubmissionType::class, null, [
            'competition_id' => $id,
            'form_fields' => $competition->getFormFields(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            // Include competition ID in the submitted data
            $submissionData = array_merge(['competition_id' => $id], $formData);
            dump($submissionData); // TODO: Process the form submission (Redis, RabbitMQ)
        }

        return $this->render('public/submit_form.html.twig', [
            'competition' => $competition,
            'form' => $form->createView(),
        ]);
    }
}
