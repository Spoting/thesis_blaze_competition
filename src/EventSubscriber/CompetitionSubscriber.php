<?php

namespace App\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use App\Entity\Competition;
use App\Service\AnnouncementService;
use App\Service\MercurePublisherService;
use App\Service\RedisManager;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Psr\Log\LoggerInterface;

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
            $changes = $this->changeLog[$hash]['changes'];

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
                    case ('status'): // Check Status Change and Publish Mercure Updates
                        if ($old != $new && in_array($new, Competition::PUBLIC_STATUSES)) {
                            // Add Announcement to Redis
                            $message = sprintf('Competition "%s" %s!', $entity->getTitle(), Competition::STATUSES[$new]);
                            $this->announcementService->addAnnouncement($new, $message);
        
                            // Publish Updates
                            $this->publisher->publishAnnouncement($new, $message);
                            $this->publisher->publishCompetitionUpdate($entity);
                            // $this->logger->error('Mercure publishCompetitionUpdate error: ' . $e->getMessage() . "|||" . $e->getTraceAsString());
                        }
                        break;
                    case ('startDate'): 
                    case ('endDate'):
                        if ($entity->getStatus() == 'scheduled') {
                            
                        }
                        break;
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

        // RealTime Update Competition -- No need. All Competitions will be Draft
        // if ($entity->getStatus() != 'draft') {
        //     // Add Announcement to Redis
        //     $message = sprintf('Competition "%s" %s!', $entity->getTitle(), Competition::STATUSES[$entity->getStatus()]);
        //     $this->announcementService->addAnnouncement($entity->getStatus(), $message);

        //     // RealTime Update
        //     $this->publisher->publishAnnouncement($entity->getStatus(), $message);
        //     $this->publisher->publishCompetitionUpdate($entity);
        // }
    }
}
