<?php

namespace App\Controller\Admin;

use App\Entity\Competition;
use App\Entity\User;
use App\Form\Admin\CompetitionFormFieldType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use Doctrine\ORM\QueryBuilder;
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
        yield ChoiceField::new('status')
            ->setChoices(array_flip($statuses))
            ->setLabel('Status')
            ->renderAsNativeWidget(true)
            ->setFormTypeOptions([
                'choice_attr' => function ($choice, $key, $value) {
                    // TODO: Add conditions for allowing Status to be setted.

                    if (!$this->isGranted('ROLE_MANAGER_ADMIN')) {
                        if (!in_array($key, ['cancelled'])) {
                            return ['disabled' => 'disabled'];
                        }
                    }

                    // For other options, return an empty array or no attributes
                    return [];
                },
            ]);

        yield DateTimeField::new('startDate')->setLabel('Submission Start');
        yield DateTimeField::new('endDate')->setLabel('Submission Deadline')
            ->setFormTypeOptions([
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\GreaterThanOrEqual([
                        'propertyPath' => 'parent.all[startDate].data',
                        'message' => 'The end date must be after or equal to the start date.',
                    ]),
                ],
            ]);
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
