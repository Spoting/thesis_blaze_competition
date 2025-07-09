<?php

namespace App\Repository;

use App\Entity\Winner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Winner>
 */
class WinnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Winner::class);
    }

    /**
     * Counts the number of winners for a given competition ID.
     * This is useful if you only have the ID and not the Competition object itself.
     *
     * @param int $competitionId The ID of the Competition.
     * @return int The count of winners.
     */
    public function countByCompetitionId(int $competitionId): int
    {
        return $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
