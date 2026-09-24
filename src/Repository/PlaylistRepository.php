<?php

namespace App\Repository;

use App\Entity\Playlist;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Playlist>
 */
class PlaylistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Playlist::class);
    }

    public function findAllWithSongCount(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.playlistSongs', 'ps')
            ->addSelect('COUNT(ps.id) as songCount')
            ->groupBy('p.id')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUser(?User $user): array
    {
        $qb = $this->createQueryBuilder('p');
        if ($user !== null) {
            $qb->where('p.createdBy = :user OR p.createdBy IS NULL')
               ->setParameter('user', $user);
        }
        return $qb->orderBy('p.name', 'ASC')->getQuery()->getResult();
    }
}