<?php

namespace App\EventSubscriber;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    /** @var UserPasswordHasherInterface */
    protected $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        // $this->userPasswordHasher = $userPasswordHasher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // BeforeEntityPersistedEvent::class => 'onBeforeEntityPersistedEvent',
            // BeforeEntityUpdatedEvent::class => 'onBeforeEntityPersistedEvent',
        ];
    }

    public function onBeforeEntityPersistedEvent(BeforeEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!($entity instanceof User)) {
            return;
        }
    }
}
