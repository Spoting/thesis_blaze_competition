<?php

namespace App\Controller\Admin;

use App\Entity\Competition;
use App\Repository\WinnerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WinnerController extends AbstractController
{
    public function __construct(
        private WinnerRepository $winnerRepository,
    ) {}

    /**
     * Displays the winners for a specific competition.
     */
    #[Route('/admin/competition/{id}/winners', name: 'admin_competition_winners', methods: ['GET'])]
    public function showCompetitionWinners(Competition $competition): Response
    {

        // Fetch winners for this competition, ordered by rank
        $winners = $this->winnerRepository->findBy(
            ['competition' => $competition],
            ['rank' => 'ASC']
        );

        return $this->render('admin/competition_winners.html.twig', [
            'competition' => $competition,
            'winners' => $winners,
        ]);
    }
}
