<?php

namespace App\MessageHandler;

use App\Entity\Competition;
use App\Message\CompetitionUpdateStatusMessage;
use App\Service\CompetitionStatusManagerService;
use App\Service\MessageProducerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class CompetitionUpdateStatusMessageHandler
{
    private $output;

    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private MessageProducerService $messageProducerService,
        private CompetitionStatusManagerService $competitionStatusManager
    ) {
        $this->output = new ConsoleOutput();
    }

    public function __invoke(CompetitionUpdateStatusMessage $message): void
    {
        $competitionId = $message->getCompetitionId();
        $targetStatus = $message->getTargetStatus();
        $messageCreationDate = $message->getMessageCreationDate();
        $delayTime = $message->getDelayTime();

        $this->output->writeln(sprintf(
            'Attempting status update for competition ID: %s. Target Status: %s. Message created: %s. Delayed by: %d seconds.',
            $competitionId,
            $targetStatus,
            $messageCreationDate,
            $delayTime
        ));


        try {
            $this->entityManager->getConnection();

            /** @var Competition */
            $competition = $this->entityManager->getRepository(Competition::class)->find($competitionId);
            $currentStatus = $competition->getStatus();

            // If current status is Higher than current. Dont attempt retry
            $isCurrentStatusHigher = $this->competitionStatusManager->isCurrentStatusEqualOrHigherThanNew($currentStatus, $targetStatus);
            if ($isCurrentStatusHigher) {
                throw new UnrecoverableMessageHandlingException(sprintf(
                    'Invalid status ( Higher current Status ) for competition %s. Current status: %s, Target: %s.',
                    $competitionId,
                    $competition->getStatus(),
                    $targetStatus
                ));
            }
            
            // If current status is Lower but the Transistion isnt Valid, then attempt retry.
            // This means that the transistions didnt trigger as planned, yet. 
            $isTransitionValid = $this->competitionStatusManager->isStatusTransitionValid($currentStatus, $targetStatus);
            $isCurrentStatusLower = $this->competitionStatusManager->isCurrentStatusLowerThanNew($currentStatus, $targetStatus);
            if ($isCurrentStatusLower && !$isTransitionValid) {
                throw new \Exception(sprintf(
                    'Invalid status ( Lower current Status ) transition for competition %s. Current status: %s, Target: %s.',
                    $competitionId,
                    $competition->getStatus(),
                    $targetStatus
                ));
            }

            // Update Competition
            $competition->setStatus($targetStatus);
            $this->entityManager->flush();
        } catch (\Doctrine\DBAL\Exception\ConnectionException $ce) {  //  | \Doctrine\DBAL\Exception\DriverException 
            $this->output->writeln(sprintf('Connection Failed %s . %s', $ce->getMessage(), get_class($ce)));

            throw $ce;
        } catch (\Throwable $e) {

            $this->output->writeln(sprintf(
                'Error during Updating Status %s for competition %s: %s . %s',
                $targetStatus,
                $competitionId,
                $e->getMessage(),
                get_class($e)
            ));

            $this->entityManager->getConnection()->close();

            throw $e;
        } finally {
            $this->entityManager->clear();
        }


        // Publish Message Email Notification to Organizer
        $organizerEmail = $competition->getCreatedBy()?->getEmail();
        $emailSubject = 'Notification: Competition Status Update';
        $emailText = 'Status Update - ' . $competition->getTitle() . ' is ' . Competition::STATUSES[$competition->getStatus()];
        if (!empty($organizerEmail)) {
            $this->messageProducerService->produceEmailNotificationMessage(
                $competitionId,
                $organizerEmail,
                $emailSubject,
                ['text' => $emailText],
                2 // High Priority
            );
        }


        $this->output->writeln(sprintf('Status Update Done for competition: %s', $competitionId));
    }
}
