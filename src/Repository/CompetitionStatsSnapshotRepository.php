<?php

namespace App\Repository;

use App\Entity\CompetitionStatsSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompetitionStatsSnapshot>
 */
class CompetitionStatsSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompetitionStatsSnapshot::class);
    }

    /**
     * Finds historical snapshots for a given competition within a specified time range.
     *
     * @param int $competitionId The ID of the competition.
     * @param \DateTimeImmutable|null $since The start date/time for the historical data. If null, fetches all.
     * @return CompetitionStatsSnapshot[] An array of snapshot entities, ordered by capture time.
     */
    public function findSnapshotsForCompetition(int $competitionId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('s.capturedAt', 'ASC');
            
        return $qb->getQuery()->getResult();
    }
}
