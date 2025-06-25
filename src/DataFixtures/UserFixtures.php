<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    private UserPasswordHasherInterface $passwordHasher;
    
    // Define role constants for clarity and reusability
    public const ROLE_COMPETITION_MANAGER = 'ROLE_COMPETITION_MANAGER';
    public const ROLE_MANAGER_ADMIN = 'ROLE_MANAGER_ADMIN';
    public const ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

     public static function getGroups(): array
     {
         return ['user_group'];
     }

    public function load(ObjectManager $obj_manager): void
    {
        $defaultPassword = '1234';

        // 1. Competition Manager
        for ($i = 1; $i < 4; $i++) {
            $competitionManager = new User();
            $competitionManager->setEmail("cm_$i@symfony.com");
            $competitionManager->setRoles([self::ROLE_COMPETITION_MANAGER]);
            $competitionManager->setPassword(
                $this->passwordHasher->hashPassword(
                    $competitionManager,
                    $defaultPassword
                )
            );
            $obj_manager->persist($competitionManager);
            $this->addReference('user_competition_manager_' . $i, $competitionManager);
        }
        // You can add a reference if you need this user in other fixtures

        // 2. Manager Admin
        for ($i = 1; $i < 3; $i++) {
            $managerAdmin = new User();
            $managerAdmin->setEmail("ma_$i@example.com");
            // A Manager Admin might also have the Competition Manager role, or other specific roles
            $managerAdmin->setRoles([self::ROLE_MANAGER_ADMIN]);
            $managerAdmin->setPassword(
                $this->passwordHasher->hashPassword(
                    $managerAdmin,
                    $defaultPassword
                )
            );
            $obj_manager->persist($managerAdmin);
            $this->addReference('user_manager_admin_' . $i, $managerAdmin);
        }

        // 3. Admin (Super Admin)
        $adminUser = new User();
        $adminUser->setEmail('admin@symfony.com');
        // The main admin usually has the highest level of access.
        // In Symfony, ROLE_ADMIN often implies broad permissions, or you might have a ROLE_SUPER_ADMIN.
        // Ensure your security.yaml correctly interprets these roles.
        $adminUser->setRoles([self::ROLE_ADMIN]);
        $adminUser->setPassword(
            $this->passwordHasher->hashPassword(
                $adminUser,
                $defaultPassword
            )
        );
        $obj_manager->persist($adminUser);
        $this->addReference('user_admin', $adminUser);


        $obj_manager->flush();

    }
}
