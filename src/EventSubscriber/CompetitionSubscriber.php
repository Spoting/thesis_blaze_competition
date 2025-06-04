<?php

namespace App\EventSubscriber;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use App\Entity\Competition;
use App\Service\MercureService;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;

#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postPersist)]
class CompetitionSubscriber
{
    private MercureService $mercure;
    public function __construct(MercureService $mercure)
    {
        $this->mercure = $mercure;
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Competition) {
            return;
        }
        $this->mercure->publishCompetitionUpdate($entity);
        // $this->cache->delete('all_competitions');
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Ensure we are only dealing with the Competition entity
        if (!$entity instanceof Competition) {
            return;
        }
        // $this->cache->delete('all_competitions');
    }
}
