<?php

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Event\SwitchUserEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\SecurityEvents;



class SwitchUserSubscriber implements EventSubscriberInterface
{
    private $security;
    private $roleHierarchy;

    public function __construct(Security $security, RoleHierarchyInterface $roleHierarchy)
    {
        $this->roleHierarchy = $roleHierarchy;
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::SWITCH_USER => 'onSwitchUser',
        ];
    }

    public function onSwitchUser(SwitchUserEvent $event): void
    {
        $targetUser = $event->getTargetUser();
        $currentUser = $this->security->getUser();

        $switchUserParameter = $event->getRequest()->query?->get('_switch_user');
        if ( // Do not Attempt to Check Roles when Exiting
            $switchUserParameter == '_exit'
        ) {
            return;
        }

        if (
            $targetUser instanceof \Symfony\Component\Security\Core\User\UserInterface
            && $currentUser instanceof \Symfony\Component\Security\Core\User\UserInterface
        ) {
            $currentUserRoles = $currentUser->getRoles();
            $targetUserRoles = $targetUser->getRoles();

            // Determine if the target user's role is higher
            if ($this->isRoleHigher($currentUserRoles, $targetUserRoles)) {
                throw new AccessDeniedException('You are not allowed to switch to a user with a higher role.');
            }
        }
    }

    private function isRoleHigher(array $currentRoles, array $targetRoles): bool
    {
        $reachableCurrentRoles = $this->roleHierarchy->getReachableRoleNames($currentRoles);
        $reachableTargetRoles = $this->roleHierarchy->getReachableRoleNames($targetRoles);

        // Check if any of the target user's reachable roles are not reachable by the current user
        foreach ($reachableTargetRoles as $targetRole) {
            if (!in_array($targetRole, $reachableCurrentRoles)) {
                return true; // Target user has a role that the current user does not
            }
        }

        return false; // Target user does not have a higher role
    }
}
