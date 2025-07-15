<?php

namespace App\Controller\Admin;

use App\Entity\Competition;
use App\Entity\User;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionStatsSnapshotRepository;
use App\Repository\SubmissionRepository;
use App\Service\CompetitionChartService;
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
class DashboardController extends AbstractDashboardController
{
    // Inject the new CompetitionChartService
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionChartService $competitionChartService
    ) {}

    public function index(): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Fetch only running competitions created by the current user
        $runningCompetitions = $this->competitionRepository->findBy([
            'status' => 'running',
            'createdBy' => $currentUser, // Filter by the current user
        ]);
        
        $charts = []; // Array to hold data for each chart
        
        // Loop through each running competition and use the service to build chart data
        foreach ($runningCompetitions as $competition) {
            $charts[] = $this->competitionChartService->buildCompetitionChartData(
                $competition,
                'Submission Trends' // Title prefix for dashboard charts
            );
        }

        // Render the main dashboard template, passing all chart data and user info
        return $this->render('admin/main_dashboard.html.twig', [
            'charts_data' => $charts, // Array of chart data for each running competition
            'user_identifier' => $currentUser->getUserIdentifier(), // Current logged-in user
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Blaze Competition');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Users', 'fas fa-list', User::class)->setPermission('ROLE_MANAGER_ADMIN');
        yield MenuItem::linkToCrud('Competitions', 'fas fa-trophy', Competition::class)->setPermission('ROLE_COMPETITION_MANAGER');
        yield MenuItem::linkToExitImpersonation('Exit impersonation', 'fas fa-sign-out-alt')->setPermission('IS_IMPERSONATOR');
    }
}
