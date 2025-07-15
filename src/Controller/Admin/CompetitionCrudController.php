<?php

namespace App\Controller\Admin;

use App\Entity\Competition;
use App\Entity\User;
use App\Form\Admin\CompetitionFormFieldType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
// use App\Form\Admin\CompetitionFormFieldType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class CompetitionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Competition::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Competition Name');
        $statuses = Competition::STATUSES;
        $statusField = ChoiceField::new('status')
            ->setChoices(array_flip($statuses))
            ->setLabel('Status')
            ->renderAsNativeWidget(true)
            ->setFormTypeOptions([
                'choice_attr' => function ($choice, $key, $value) {
                    // TODO: Add conditions for allowing Status to be setted.

                    if (!$this->isGranted('ROLE_MANAGER_ADMIN')) {
                        if (!in_array($value, ['cancelled', 'draft', 'scheduled'])) {
                            return ['disabled' => 'disabled'];
                        }
                    }

                    // For other options, return an empty array or no attributes
                    return [];
                },
            ]);



        $startDateField = DateTimeField::new('startDate')
            ->setTimezone('UTC')
            ->setFormTypeOption('model_timezone', 'UTC')
            ->setFormTypeOption('view_timezone', 'Europe/Athens');
        $endDateField = DateTimeField::new('endDate')
            ->setTimezone('UTC')
            ->setFormTypeOption('model_timezone', 'UTC')
            ->setFormTypeOption('view_timezone', 'Europe/Athens');

        $startDateConstraints = [];
        $endDateConstraints = [
            new GreaterThan([
                'propertyPath' => 'parent.all[startDate].data',
                'message' => 'The end date must be after to the start date.',
            ])
        ];


        if (in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT])) {
            $startDateField->setLabel('Submissions Start (Athens Time)');
            $endDateField->setLabel('Submissions End (Athens Time)');
        } else {
            $startDateField->setLabel('Start Date (UTC)');
            $endDateField->setLabel('End Date (UTC)');
        }

        if (Crud::PAGE_NEW === $pageName) {
            if (!$this->isGranted('ROLE_MANAGER_ADMIN')) {
                // Disable Status field so all New Competitions will be 'draft'
                $statusField->setDisabled();
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $startDateConstraints[] = new GreaterThan([
                'value' => $now->modify('+1 minutes'),
                'message' => 'Start time must be at least 1 minutes in the future.',
            ]);
        }

        if (Crud::PAGE_EDIT === $pageName) {
            $entity = $this->getContext()?->getEntity()?->getInstance();
            if ($entity && $entity->getStatus() != 'draft') {
                // Dont allow Organizer to Change Start|End dates when the Competition is Published.
                $startDateField->setFormTypeOption('disabled', true);
                $endDateField->setFormTypeOption('disabled', true);

                // Dont change the Status after the Automations are scheduled.
                if ($entity->getStatus() != 'scheduled') {
                    if (!$this->isGranted('ROLE_MANAGER_ADMIN')) {
                        $statusField->setDisabled();
                    }
                }
            }
        }

        yield $statusField;
        yield $startDateField->setFormTypeOption('constraints', $startDateConstraints);
        yield $endDateField->setFormTypeOption('constraints', $endDateConstraints);


        yield TextareaField::new('description')->hideOnIndex();
        yield TextareaField::new('prizes')->setHelp('Describe the prizes for this competition.')->hideOnIndex();
        yield IntegerField::new('numberOfWinners')->setLabel('Number of Winners');
        // yield IntegerField::new('maxParticipants');

        yield CollectionField::new('formFields', 'Competition Form Fields')
            ->setEntryType(CompetitionFormFieldType::class)
            ->setFormTypeOptions([
                'by_reference' => false,
            ])
            ->allowAdd(true)
            ->allowDelete(true)
            ->renderExpanded()
            ->setHelp('Define the fields for the public submission form. Default email and phone fields are pre-filled for new competitions (you can modify or remove them).')
            ->onlyOnForms();

        yield AssociationField::new('createdBy')->setPermission('ROLE_MANAGER_ADMIN');
        yield DateTimeField::new('createdAt')->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnIndex();
    }


    public function configureActions(Actions $actions): Actions  
    {
        // Define the custom action to view competition stats
        $viewStats = Action::new('viewStats', 'View Stats', 'fa fa-chart-bar')
            // Link this action to your custom route for competition stats
            ->linkToRoute('admin_competition_stats', function (Competition $competition) {
                return ['id' => $competition->getId()];
            })
            ->addCssClass('btn btn-info')
            ->setHtmlAttributes(['title' => 'View Competition Statistics Chart']);

        return $actions
            ->add(Crud::PAGE_INDEX, $viewStats)
            ->add(Crud::PAGE_DETAIL, $viewStats)
            // Set permissions for other default actions as per your security roles
            ->setPermission(Action::NEW, 'ROLE_COMPETITION_MANAGER')
            ->setPermission(Action::EDIT, 'ROLE_COMPETITION_MANAGER')
            ->setPermission(Action::DELETE, 'ROLE_MANAGER_ADMIN'); // Only admins can delete
    }


    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // Show only competitions created by the Competition Manager.
        if (in_array('ROLE_COMPETITION_MANAGER', $this->getUser()->getRoles())) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $queryBuilder->andWhere("$rootAlias.createdBy = :user")
                ->setParameter('user', $this->getUser());
        }

        return $queryBuilder;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return;
        }
        if (!$entityInstance instanceof Competition) {
            return;
        }

        // Assign CreatedBy of Current User.
        $entityInstance->setCreatedBy($user);
        parent::persistEntity($entityManager, $entityInstance);
    }

    // public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    // {
    //     parent::updateEntity($entityManager, $entityInstance);
    // }
}
