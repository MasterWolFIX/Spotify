<?php

namespace App\Repository;

use App\Entity\SongPlay;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SongPlay>
 */
class SongPlayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SongPlay::class);
    }

    /**
     * @return SongPlay[]
     */
    public function findRecent(int $limit = 10, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('sp')
            ->innerJoin('sp.song', 's')
            ->addSelect('s')
            ->orderBy('sp.playedAt', 'DESC')
            ->setMaxResults($limit);

        if ($user !== null) {
            $qb->andWhere('sp.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function getTotalPlaysCount(?User $user = null): int
    {
        $qb = $this->createQueryBuilder('sp')
            ->select('COUNT(sp.id)');

        if ($user !== null) {
            $qb->andWhere('sp.user = :user')
               ->setParameter('user', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getTotalDuration(?User $user = null): int
    {
        $qb = $this->createQueryBuilder('sp')
            ->select('COALESCE(SUM(sp.duration), 0)');

        if ($user !== null) {
            $qb->andWhere('sp.user = :user')
               ->setParameter('user', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
