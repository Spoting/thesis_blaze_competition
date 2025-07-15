<?php

namespace App\Controller\Admin;

use App\Entity\Competition;
use App\Service\CompetitionChartService; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CompetitionStatsController extends AbstractController
{
    public function __construct(
        private CompetitionChartService $competitionChartService
    ) {}

    /**
     * Renders the custom EasyAdmin page to display the competition statistics graph.
     * This method now uses the CompetitionChartService to build the Chart object.
     */
    #[Route('/admin/competition/{id}/stats', name: 'admin_competition_stats', methods: ['GET'])]
    public function showCompetitionStats(Competition $competition): Response
    {
        // Use the service to build the chart data for this specific competition
        // The service will handle fetching snapshots and current data, and building the Chart object.
        $chartData = $this->competitionChartService->buildCompetitionChartData(
            $competition,
            'Submission Statistics for ' . $competition->getTitle(),
        );

        // The service returns an array, extract the chart and mercureUrl from it
        $chart = $chartData['chart'];
        $mercureUrl = $chartData['mercureUrl'];


        // Render the Twig template, passing all necessary data for chart rendering and updates.
        return $this->render('admin/competition_stats.html.twig', [
            'competition' => $competition, // The competition entity itself.
            'chart' => $chart, // The Symfony UX Chart object.
            'mercureUrl' => $mercureUrl, // Mercure URL for real-time updates.
        ]);
    }
}
