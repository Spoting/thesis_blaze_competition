<?php

namespace App\Repository;

use App\Entity\Competition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @extends ServiceEntityRepository<Competition>
 */
class CompetitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private TagAwareCacheInterface $resultCachePool)
    {
        parent::__construct($registry, Competition::class);
    }

    /**
     * Retrieves competitions that are in the given public statuses.
     *
     * @param array $publicStatuses An array of statuses considered public (e.g., ['scheduled', 'running']).
     * @return Competition[] Returns an array of Competition objects.
     */
    public function findByPublicStatuses(array $publicStatuses): array
    {
        $cacheKey = 'competitions_public_' . md5(implode(',', $publicStatuses));

        // Use Symfony's cache->get() method to wrap your Doctrine query
        return $this->resultCachePool->get($cacheKey, function (ItemInterface $item) use ($publicStatuses) {
            // This callback is only executed on a cache MISS.

            // 1. Set the cache lifetime
            $item->expiresAfter(3600); // Cache for 1 hour

            // 2. ✅ This is the magic part: add one or more tags
            $item->tag(['competitions_list']);

            // 3. Execute your actual Doctrine query and return the result
            return $this->createQueryBuilder('c')
                ->andWhere('c.status IN (:publicStatuses)')
                ->setParameter('publicStatuses', $publicStatuses)
                ->getQuery()
                ->getResult();
        });

        // return $this->createQueryBuilder('c')
        //     ->andWhere('c.status IN (:publicStatuses)')
        //     ->setParameter('publicStatuses', $publicStatuses)
        //     ->getQuery()
        //     ->enableResultCache(3600, "competition_list")
        //     ->getResult();
    }

    //    /**
    //     * @return Competition[] Returns an array of Competition objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Competition
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
