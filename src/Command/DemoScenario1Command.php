<?php

namespace App\Command;

use App\Entity\Competition;
use App\Entity\User;
use App\Repository\CompetitionRepository;
use App\Repository\CompetitionStatsSnapshotRepository;
use App\Repository\SubmissionRepository;
use App\Repository\UserRepository;
use App\Repository\WinnerRepository;
use App\Service\MercurePublisherService; // Still needed for initial competition status updates
use App\Service\MessageProducerService; // To produce real submission messages
use App\Service\RedisKeyBuilder;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:demo-scenario-1',
    description: 'Demonstrates competition lifecycle and real-time submission charts by producing real RabbitMQ messages.',
)]
class DemoScenario1Command extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private CompetitionStatsSnapshotRepository $competitionStatsSnapshotRepository,
        private SubmissionRepository $submissionRepository,
        private WinnerRepository $winnerRepository, // NEW: Inject WinnerRepository
        private RedisManager $redisManager,
        private RedisKeyBuilder $redisKeyBuilder,
        private MercurePublisherService $mercurePublisherService,
        private MessageProducerService $messageProducerService,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting Demo Scenario 1: Competition Lifecycle & Real-time Charts with Real Messages');

        // --- 1. Get an Organizer User ---
        /** @var User|null $organizerUser */
        $organizerUser = $this->userRepository->findOneBy(['email' => 'admin@symfony.com']);

        if (!$organizerUser) {
            $io->error('No organizer user found. Please create a user with email "	admin@symfony.com" or change the email in the command.');
            return Command::FAILURE;
        }
        $io->text(sprintf('Using organizer user: %s', $organizerUser->getUserIdentifier()));


        // --- NEW: Clear existing competitions with the same demo titles ---
        $io->section('Clearing Existing Demo Competitions...');
        $this->clearExistingCompetitions([
            'Demo Comp A (10s Start)',
            'Demo Comp B (20s Start)',
            'Demo Comp C (30s Start)',
            'Demo Comp D (10s Start)', // Include all demo competition titles
        ]);
        $io->success('Existing demo competitions and their data cleared.');

        // --- 2. Create 3 Competitions with Specific Timings ---
        $io->section('Creating Competitions...');
        $competitions = [];
        $now = new \DateTime();

        // $comp1 = $this->createCompetition(
        //     'Demo Comp A (10s Start)',
        //     $now->modify('+10 seconds'),
        //     $now->modify('+4 minutes'),
        //     $organizerUser
        // );
        // $competitions[] = $comp1;
        // $this->updateCompetition($comp1);
        // $io->text(sprintf('Created Competition A (ID: %d): Starts in 10s, Ends in 4m', $comp1->getId()));

        // $comp2 = $this->createCompetition(
        //     'Demo Comp B (20s Start)',
        //     $now->modify('+20 seconds'),
        //     $now->modify('+2 minutes'),
        //     $organizerUser
        // );
        // $this->updateCompetition($comp2);
        // $competitions[] = $comp2;
        // $io->text(sprintf('Created Competition B (ID: %d): Starts in 20s, Ends in 2m', $comp2->getId()));

        // $comp3 = $this->createCompetition(
        //     'Demo Comp C (30s Start)',
        //     $now->modify('+30 seconds'),
        //     $now->modify('+1 minutes'),
        //     $organizerUser
        // );
        // $this->updateCompetition($comp3);
        // $competitions[] = $comp3;
        // $io->text(sprintf('Created Competition C (ID: %d): Starts in 30s, Ends in 1m', $comp3->getId()));

        $comp4 = $this->createCompetition(
            'Demo Comp D (10s Start)',
            $now->modify('+10 seconds'),
            $now->modify('+130 seconds'),
            $organizerUser
        );
        $this->updateCompetition($comp4);
        $competitions[] = $comp4;
        $io->text(sprintf('Created Competition D (ID: %d): Starts in 10s, Ends in 130s', $comp4->getId()));

        $this->entityManager->flush(); // Persist all competitions

        $io->success('Competitions created and persisted.');

        // --- 3. Simulate Submission Activity by Producing Real Messages ---
        $io->section('Simulating Real Submission Activity...');
        $io->text('Keep your EasyAdmin dashboard open (e.g., /admin) and watch the charts.');
        $io->text('Ensure your Messenger consumers are running.');
        $io->text('Ensure Scheduler/Cronjob is running to capture snapshots.');

        $simulationDuration = 120; // Total simulation time in seconds
        $interval = 1; // Simulate activity every 5 seconds (produce messages every X seconds)
        $submissionsPerInterval = 1000; // Number of submissions to produce per interval
        $failedSubmissionRate = 0.1; // 10% of submissions will be marked as "failed" for demo

        $totalSubmissionsProduced = 0;


        for ($time = 0; $time <= $simulationDuration; $time += $interval) {
            $io->text(sprintf("\n--- Simulating at T+%d seconds ---", $time));
            sleep($interval); // Pause for real-time observation

            foreach ($competitions as $comp) {
                // Only produce messages for competitions that are 'running' or 'scheduled'
                if ($comp->getStatus() === 'running' || $comp->getStatus() === 'scheduled') {
                    $io->text(sprintf('  Producing %d messages for Comp %s (ID: %d)...', $submissionsPerInterval, $comp->getTitle(), $comp->getId()));

                    for ($i = 0; $i < $submissionsPerInterval; $i++) {
                        $dummyCompetitionEndTimestamp = $comp->getEndDate()->getTimestamp();
                        $priorityKey = $this->messageProducerService->identifyPriorityKey($dummyCompetitionEndTimestamp);

                        $email = sprintf('user_%d_%d_%s@example.com', $comp->getId(), $totalSubmissionsProduced, uniqid('la'));
                        $formData = [
                            'email' => $email,
                            'phoneNumber' => '696969690' . $priorityKey,
                            'priorityKey' => $priorityKey
                        ];

                        // // Simulate a "failed" submission by sending it to the DLQ directly for demo purposes
                        // // In a real app, this would happen if the consumer fails and retries are exhausted.
                        // $isFailedDemo = (mt_rand(0, 100) / 100) < $failedSubmissionRate;

                        // // For the demo, we'll use a dummy competition end timestamp for priority calculation.
                        // // In a real scenario, this comes from the competition entity.
                        // if ($isFailedDemo) {
                        //     // To simulate a message going to DLQ, we would normally let the consumer fail.
                        //     // For this demo, we can produce a message that's *intended* to fail for demonstration.
                        //     // However, Messenger doesn't have a direct "produce to DLQ" method.
                        //     // The most realistic way to demo DLQ is to have a consumer that *sometimes* throws an exception.
                        //     // For now, we'll just produce regular messages and let the system naturally handle failures.
                        //     // The DLQ chart will reflect actual failures from your consumers.
                        // }

                        $this->messageProducerService->produceSubmissionMessage(
                            (string) $dummyCompetitionEndTimestamp,
                            $formData,
                            $comp->getId(),
                            $email
                        );
                        $this->redisManager->incrementValue($this->redisKeyBuilder->getCompetitionCountKey($comp->getId()));

                        $totalSubmissionsProduced++;
                    }
                    $io->text(sprintf('  Produced %d messages for Comp %s (ID: %d). Total produced: %d', $submissionsPerInterval, $comp->getTitle(), $comp->getId(), $totalSubmissionsProduced));
                } else {
                    $io->text(sprintf('  Comp %s (ID: %d): Status "%s", not running or scheduled.', $comp->getTitle(), $comp->getId(), $comp->getStatus()));
                }
            }
        }

        $io->section('Demo Scenario 1 Finished.');
        $io->success('Demonstration complete!');

        return Command::SUCCESS;
    }

    /**
     * Helper to create and persist a Competition entity.
     */
    private function createCompetition(string $title, \DateTime $startDate, \DateTime $endDate, User $createdBy): Competition
    {
        $competition = new Competition();
        $competition->setTitle($title);
        $competition->setDescription('Demo competition for scenario 1.');
        $competition->setPrizes('Demo Prizes');
        $competition->setStartDate($startDate);
        $competition->setEndDate($endDate);
        $competition->setMaxParticipants(100000);
        $competition->setNumberOfWinners(rand(1, 5));
        $competition->setStatus('draft'); // Start as draft
        $competition->setCreatedBy($createdBy);

        $this->entityManager->persist($competition);
        $this->entityManager->flush();

        // Reset Redis counter for this competition for a clean demo
        // This is important because the initial submission controller increments it.
        $this->redisManager->setValue($this->redisKeyBuilder->getCompetitionCountKey($competition->getId()), 0);

        return $competition;
    }

    private function updateCompetition(Competition $competition) 
    {
        $competition->setStatus('scheduled');
        $this->entityManager->flush();
    }


      /**
     * Clears existing demo competitions and their associated data.
     *
     * @param array $titlesToDelete An array of competition titles to delete.
     */
    private function clearExistingCompetitions(array $titlesToDelete): void
    {
        // Find existing competitions by title
        $existingCompetitions = $this->competitionRepository->findBy(['title' => $titlesToDelete]);

        if (empty($existingCompetitions)) {
            // $this->logger->info('No existing demo competitions found to clear.');
            return;
        }

        // $this->logger->info(sprintf('Clearing %d existing demo competitions and their data.', count($existingCompetitions)));

        foreach ($existingCompetitions as $competition) {
            // $this->logger->info(sprintf('  Deleting data for Competition: %s (ID: %d)', $competition->getTitle(), $competition->getId()));
            $competitionId = $competition->getId();
            // 1. Delete CompetitionStatsSnapshot records
            $snapshots = $this->competitionStatsSnapshotRepository->findBy(['competition' => $competition]);
            foreach ($snapshots as $snapshot) {
                $this->entityManager->remove($snapshot);
            }
            $this->entityManager->flush();
            // $this->logger->info(sprintf('    Removed %d snapshots.', count($snapshots)));

            // 2. Delete Submission records
            $submissions = $this->submissionRepository->findBy(['competition' => $competition]);
            foreach ($submissions as $submission) {
                $this->entityManager->remove($submission);
            }
            $this->entityManager->flush();
            // NEW: 3. Delete Winner records
            $winners = $this->winnerRepository->findBy(['competition' => $competition]);
            foreach ($winners as $winner) {
                $this->entityManager->remove($winner);
            }
            // 3. Delete the Competition itself
            $this->entityManager->remove($competition);
            $this->entityManager->flush();
            $this->entityManager->clear();
            // $this->logger->info('    Removed Competition entity.');

            // 4. Reset Redis counter for this competition
            $this->redisManager->deleteKey($this->redisKeyBuilder->getCompetitionCountKey($competitionId));
            // $this->logger->info('    Cleared Redis counter.');
        }

        $this->entityManager->flush(); // Flush all deletions in one transaction
        // $this->logger->info('All demo competition data flushed from database.');
    }
}


        // Adjusted thresholds for demonstration
        // if ($timeRemainingSeconds <= 10) { // Less than 10 seconds
        //     return 5;
        // } elseif ($timeRemainingSeconds <= 20) { // Less than 20 seconds
        //     return 4;
        // } elseif ($timeRemainingSeconds <= 30) { // Less than 30 seconds
        //     return 3;
        // } elseif ($timeRemainingSeconds <= 60) { // Less than 1 minute
        //     return 2;
        // } elseif ($timeRemainingSeconds <= 120) { // Less than 2 minutes
        //     return 1;
        // } else {
        //     return 0; // 2 minutes and up
        // }