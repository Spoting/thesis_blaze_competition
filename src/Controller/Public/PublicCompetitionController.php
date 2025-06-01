<?php 

// src/Controller/Public/PublicCompetitionController.php
namespace App\Controller\Public;

use App\Entity\Competition;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
            ->where('c.endDate > :now')
            ->setParameter('now', $now)
            ->orderBy('c.endDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('public/competitions.html.twig', [
            'competitions' => $competitions,
        ]);
    }
}