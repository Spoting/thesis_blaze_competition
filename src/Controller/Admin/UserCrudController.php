<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UserCrudController extends AbstractCrudController
{
    private array $roles;
    private $userPasswordHasher;
    private UserRepository $userRepository;
    private $urlGenerator;

    public function __construct(
        ParameterBagInterface $params,
        UserPasswordHasherInterface $userPasswordHasher,
        UserRepository $userRepository,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->roles = $params->get('app.roles');
        $this->userPasswordHasher = $userPasswordHasher;
        $this->userRepository = $userRepository;
        $this->urlGenerator = $urlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }


    public function configureActions(Actions $actions): Actions
    {
        $actions = parent::configureActions($actions);

        // Define the impersonate action
        $impersonateAction = Action::new('impersonate', 'Impersonate', 'fa fa-user-secret')
            ->linkToUrl(function (User $user) {
                // Generate a URL to the admin dashboard with the _switch_user parameter.
                // Symfony's SwitchUserListener will handle the actual switch.
                return $this->urlGenerator->generate('admin_dashboard', [
                    '_switch_user' => $user->getUserIdentifier(),
                ]);
            })
            ->displayIf(function (?User $user) {
                // Ensure there is a target user and the current user has the permission
                if (!$user || !$this->isGranted('ROLE_ALLOWED_TO_SWITCH')) {
                    return false;
                }
                $currentUser = $this->getUser();
                // Ensure there is a current user and don't show for the current user themselves
                return $currentUser && ($user->getUserIdentifier() !== $currentUser->getUserIdentifier());
            })
            ->setCssClass('btn btn-sm text-info mr-1'); // Optional: for styling    
        $actions->add(Crud::PAGE_INDEX, $impersonateAction);
        

        // Admins and Manager Admins can do Everything.
        if ($this->isGranted('ROLE_MANAGER_ADMIN')) {
            return $actions;
        }

        // Default fallback
        return $actions
            ->disable(Action::DELETE, Action::EDIT, Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnForms()->setDisabled();
        yield EmailField::new('email');
        yield TextField::new('plainPassword', 'Password')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type'            => PasswordType::class,
                'first_options'   => ['label' => 'Password'],
                'second_options'  => ['label' => 'Repeat password'],
                'error_bubbling'  => true,
                'invalid_message' => 'The password fields do not match.',
            ])
            ->setHelp('Leave blank to keep the current password')
            ->onlyOnForms() // Only show on create/edit forms
            ->setRequired($pageName === Crud::PAGE_NEW);

        $rolesField = ChoiceField::new('roles')
            ->setChoices($this->roles)
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setSortable(false);

        if ($this->isGranted('ROLE_ADMIN')) {
            yield $rolesField;
        } elseif ($this->isGranted('ROLE_MANAGER_ADMIN') && $pageName === Crud::PAGE_NEW) {
            yield $rolesField->setChoices(['Organizer' => 'ROLE_COMPETITION_MANAGER']);
        } else {
            yield $rolesField->onlyOnIndex(); // Only show roles on index for others
        }
    }

    // Limits Results for each Role
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $rootAlias = $queryBuilder->getRootAliases()[0];

        if (in_array('ROLE_MANAGER_ADMIN', $this->getUser()->getRoles())) {
            $matchingUserIds = $this->userRepository->findUserIdsByRole('ROLE_COMPETITION_MANAGER');

            // If no matching users, prevent error by returning empty result
            if (empty($matchingUserIds)) {
                $queryBuilder->andWhere('1 = 0'); // Always false
            } else {
                $queryBuilder->andWhere("$rootAlias.id IN (:ids)")
                    ->setParameter('ids', $matchingUserIds);
            }
        }

        return $queryBuilder;
    }

    // Used for Handling Password Field
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->getPlainPassword()) {
            $this->hashPassword($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    // Used for Handling Password Field
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }
        $plainPassword = $entityInstance->getPlainPassword();

        if (!empty($plainPassword)) {
            $this->hashPassword($entityInstance);
        } else {
            $originalEntity = $entityManager->getRepository(User::class)->find($entityInstance->getId());
            if ($originalEntity) {
                $entityInstance->setPassword($originalEntity->getPassword());
            }
        }
        // Invalidate the plainPassword after (attempted) update
        $entityInstance->setPlainPassword(null);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword(User $user): void
    {
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $user->getPlainPassword()));
    }
}
