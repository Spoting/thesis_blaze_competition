<?php

namespace App\Repository;

use App\Entity\CompetitionStatusTransition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompetitionStatusTransition>
 *
 * @method CompetitionStatusTransition|null find($id, $lockMode = null, $lockVersion = null)
 * @method CompetitionStatusTransition|null findOneBy(array $criteria, array $orderBy = null)
 * @method CompetitionStatusTransition[]    findAll()
 * @method CompetitionStatusTransition[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CompetitionStatusTransitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompetitionStatusTransition::class);
    }

    /**
     * Finds status transitions for a given competition within a specified time range.
     *
     * @param int $competitionId The ID of the competition.
     * @param \DateTimeImmutable|null $since The start date/time for the historical data. If null, fetches all.
     * @return CompetitionStatusTransition[] An array of transition entities, ordered by transition time.
     */
    public function findTransitionsForCompetition(int $competitionId, ?\DateTimeImmutable $since = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('t.transitionedAt', 'ASC');

        if ($since) {
            $qb->andWhere('t.transitionedAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }
}
