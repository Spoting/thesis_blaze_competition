<?php

namespace App\EventSubscriber;

use App\Entity\Competition;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Exception;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class EasyAdminSubscriber implements EventSubscriberInterface
{

    private Security $security;

    public function __construct(
        Security $security
    ) {
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCrudActionEvent::class => 'onBeforeCrudAction',
        ];
    }

    public function onBeforeCrudAction(BeforeCrudActionEvent $event): void
    {
        $adminContext = $event->getAdminContext();
        if (!$adminContext) {
            return;
        }

        $entityInstance = $adminContext->getEntity()->getInstance();
        $currentUser = $this->security->getUser();

        if (
            $entityInstance instanceof Competition
            // && in_array('ROLE_COMPETITION_MANAGER', $currentUser->getRoles())
            && !$this->security->isGranted('ROLE_MANAGER_ADMIN') 
        ) {
            if ($entityInstance->getCreatedBy() !== $currentUser) {
                throw new AccessDeniedException('You are not allowed Κατεργάρη');
            }
        }

        // TODO : Dont allow Users to edit other Users of the same Hierarchy Level. Except Admin ofcourse.
        // if (
        //     $entityInstance instanceof User
        //     // && in_array('ROLE_COMPETITION_MANAGER', $currentUser->getRoles())
        //     && !$this->security->isGranted('ROLE_MANAGER_ADMIN') 
        // ) {
        // }

    }
}
