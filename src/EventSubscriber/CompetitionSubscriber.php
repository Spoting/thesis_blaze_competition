<?php

namespace App\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use App\Entity\Competition;
use App\Entity\CompetitionStatsSnapshot;
use App\Entity\CompetitionStatusTransition;
use App\Service\AnnouncementService;
use App\Service\CompetitionSnapshotService;
use App\Service\CompetitionStatusManagerService;
use App\Service\MercurePublisherService;
use App\Service\MessageProducerService;
use App\Service\RedisManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// #[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postUpdate)]
class CompetitionSubscriber
{
    /** @var array<string, array{entity: object, changes: array}> */
    private array $changeLog = [];

    public function __construct(
        private MercurePublisherService $publisher,
        private RedisManager $redis,
        private AnnouncementService $announcementService,
        private LoggerInterface $logger,
        private CompetitionStatusManagerService $competitionStatusManager,
        private MessageProducerService $messageProducerService,
        private EntityManagerInterface $entityManager,
        private CompetitionSnapshotService $competitionSnapshotService,
        private StoreInterface $store, // Service for HTTP cache interaction
        private UrlGeneratorInterface $router, // Service for generating URLs
        private TagAwareCacheInterface $resultCachePool,
        private ?Security $security = null // (can be null in console)
    ) {}


    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Competition) {
            return;
        }
        $this->changeLog[spl_object_hash($entity)] = [
            'entity' => $entity,
            'changes' => $args->getEntityChangeSet(),
        ];
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Competition) {
            return;
        }

        $hash = spl_object_hash($entity);

        if (isset($this->changeLog[$hash])) {
            // Purge the relevant pages from the HTTP cache
            $this->purgeHttpCache($entity);

            // Purge competition list result
            $this->resultCachePool->invalidateTags(['competitions_list']);

            // Publish Updated Competition's Data.
            $this->publisher->publishCompetitionUpdate($entity);

            $changes = $this->changeLog[$hash]['changes'];


            $shouldDispatchStatusUpdateMessages = false;
            foreach ($changes as $field => [$old, $new]) {
                // Log Changes
                $this->logger->info(sprintf(
                    'Field "%s" of %s (ID: %s) changed from "%s" to "%s"',
                    $field,
                    get_class($entity),
                    method_exists($entity, 'getId') ? $entity->getId() : 'n/a',
                    (string) json_encode($old),
                    (string) json_encode($new)
                ));


                switch ($field) {
                    case ('status'):

                        // Check Status Change and Publish Mercure Updates
                        if ($old != $new && in_array($new, Competition::PUBLIC_STATUSES)) {
                            // If new Status is scheduled flag to Trigger Status Automations
                            if ($new == 'scheduled') {
                                $shouldDispatchStatusUpdateMessages = true;
                            }

                            // Store to Redis & Mercure Publish Announcement
                            $message = sprintf('Competition "%s" %s!', $entity->getTitle(), Competition::STATUSES[$new]);
                            $this->announcementService->addAnnouncement($new, $message);
                            $this->publisher->publishAnnouncement($new, $message);

                            // Persist the status transition to the database
                            $transition = $this->generateStatusTransitionEntity($entity, $old, $new);

                            $snapshot = $this->competitionSnapshotService->generateSnapshotEntity($entity);

                            $this->entityManager->persist($snapshot);
                            $this->entityManager->persist($transition);
                            $this->entityManager->flush();

                            $this->publisher->publishUpdateChart($entity->getId(), $snapshot);
                            $this->publisher->publishStatusTransitionAnnotation($entity, $transition);

                            $this->logger->info(sprintf('Recorded status transition for Comp %d: %s -> %s', $entity->getId(), $old, $new));
                        }

                        break;

                    case ('startDate'):
                    case ('endDate'):
                        // If Start/End Date is changed, and the current status is Scheduled,
                        // Activate flag to Trigger Status Automations
                        if ($entity->getStatus() == 'scheduled') {
                            $shouldDispatchStatusUpdateMessages = true;
                        }
                        break;
                }
            }


            if ($shouldDispatchStatusUpdateMessages) {
                $organizerEmail = $entity->getCreatedBy()->getEmail();
                $statusTransitionTimestamps = $this->competitionStatusManager->calculateStatusTransitionDelays($entity);
                foreach ($statusTransitionTimestamps as $status => $delay_ms) {
                    if ($status == 'winners_announced') {
                        $this->messageProducerService->produceWinnerTriggerMessage(
                            $entity->getId(),
                            $delay_ms,
                            $organizerEmail
                        );
                    } else {
                        $this->messageProducerService->produceCompetitionStatusUpdateMessage(
                            $entity->getId(),
                            $delay_ms,
                            $status,
                            $organizerEmail
                        );
                    }
                }
            }

            unset($this->changeLog[$hash]); // Clean up
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Ensure we are only dealing with the Competition entity
        if (!$entity instanceof Competition) {
            return;
        }

        // RealTime Update Competition -- No need. All Starting Competitions will be Draft
        // if ($entity->getStatus() != 'draft') {
        //     // Add Announcement to Redis
        //     $message = sprintf('Competition "%s" %s!', $entity->getTitle(), Competition::STATUSES[$entity->getStatus()]);
        //     $this->announcementService->addAnnouncement($entity->getStatus(), $message);

        //     // RealTime Update
        //     $this->publisher->publishAnnouncement($entity->getStatus(), $message);
        //     $this->publisher->publishCompetitionUpdate($entity);
        // }
    }

    /**
     * Purges relevant pages from the HTTP cache for a given competition.
     */
    private function purgeHttpCache(Competition $competition): void
    {
        $urlsToPurge = [];

        // 1. Generate the URL for the main competitions list page
        $urlsToPurge[] = $this->router->generate('public_competitions_list', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // 2. Generate the URL for this specific competition's submit page
        $urlsToPurge[] = $this->router->generate('public_competition_submit', [
            'id' => $competition->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($urlsToPurge as $urlToPurge) {
            $this->store->purge($urlToPurge);
        }
        $this->logger->info('Purged HTTP cache for URLs: ' . implode(', ', $urlsToPurge));
    }

    private function generateStatusTransitionEntity(Competition $competition, string $oldStatus, string $newStatus): CompetitionStatusTransition
    {
        $transition = new CompetitionStatusTransition();
        $transition->setCompetition($competition);
        $transition->setOldStatus($oldStatus);
        $transition->setNewStatus($newStatus);
        $transition->setTransitionedAt(new \DateTimeImmutable());
        // Determine who triggered the change (system or user)
        if ($this->security && $this->security->getUser()) {
            $transition->setTriggeredBy($this->security->getUser()->getUserIdentifier());
        } else {
            $transition->setTriggeredBy('system_process'); // For console commands/workers
        }

        return $transition;
    }
}
