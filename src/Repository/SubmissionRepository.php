<?php

namespace App\Repository;

use App\Entity\Submission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Submission>
 */
class SubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Submission::class);
    }

    /**
     * Counts the number of submissions for a given competition ID.
     * This is useful if you only have the ID and not the Competition object itself.
     *
     * @param int $competitionId The ID of the Competition.
     * @return int The count of submissions.
     */
    public function countByCompetitionId(int $competitionId): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * Returns an iterator for all submission IDs for a given competition.
     * This uses a database cursor (toIterable()) for memory efficiency with large result sets.
     *
     * @param int $competitionId The ID of the competition.
     * @return iterable A traversable object (iterator) yielding submission IDs.
     */
    public function getSubmissionIdsIterator(int $competitionId): iterable
    {
        return $this->createQueryBuilder('s')
            ->select('s.id') 
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('s.id', 'ASC') 
            ->getQuery()
            ->toIterable();
    }
}
