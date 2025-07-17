<?php

namespace App\DataFixtures;

use App\Entity\Competition;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

class CompetitionFixtures extends Fixture implements DependentFixtureInterface
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('en_US');
    }
    /**
     * This method must return an array of fixtures classes
     * on which the implementing class depends.
     *
     * @return array<class-string<Fixture>>
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class, // CompetitionFixtures depends on UserFixtures
        ];
    }

    public function load(ObjectManager $manager): void
    {

        $users = [
            $this->getReference('user_competition_manager_1', User::class),
            $this->getReference('user_competition_manager_2', User::class),
            $this->getReference('user_competition_manager_3', User::class)
        ];

        // Create 10 competition entries
        for ($i = 0; $i < 9; $i++) {
            $competition = new Competition();
            $competition->setTitle($this->faker->sentence(rand(3)));
            $competition->setDescription($this->faker->paragraph(rand(2, 5)));
            $competition->setPrizes($this->faker->text(200));

            // Set start and end dates
            $startDate = $this->faker->dateTimeBetween('-1 month', '+2 months');
            $endDate = (clone $startDate)->modify('+' . rand(7, 30) . ' days');
            $competition->setStartDate($startDate);
            $competition->setEndDate($endDate);

            $competition->setMaxParticipants(rand(50, 5000));

            // Randomly assign form fields, sometimes using default, sometimes custom
            if ($i % 3 === 0) {
                // Use default form fields
                $competition->setFormFields(Competition::DEFAULT_FORM_FIELDS);
            } else {
                // Mix default and some custom fields
                $mixedFields = array_merge(
                    Competition::DEFAULT_FORM_FIELDS,
                    [
                        'birthDate' => [
                            'type' => 'date',
                            'name' => 'birthDate',
                            'label' => 'Date of Birth'
                        ]
                    ]
                );
                $competition->setFormFields($mixedFields);
            }


            // Set createdBy, associating with the dummy user
            $competition->setCreatedBy($users[rand(0, 2)]);

            // Set random status from the defined constants
            $statuses = array_keys(Competition::STATUSES);
            $randomStatus = $statuses[array_rand($statuses)];
            $competition->setStatus($randomStatus);

            $competition->setNumberOfWinners(rand(1, 5));

            // Doctrine's @PrePersist and @PreUpdate will handle createdAt and updatedAt
            $manager->persist($competition);
        }

        $manager->flush();
    }
}
