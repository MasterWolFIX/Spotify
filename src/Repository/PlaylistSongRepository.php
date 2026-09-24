<?php

namespace App\Repository;

use App\Entity\PlaylistSong;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlaylistSong>
 */
class PlaylistSongRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlaylistSong::class);
    }

    public function getMaxPosition(int $playlistId): int
    {
        $result = $this->createQueryBuilder('ps')
            ->select('MAX(ps.position)')
            ->where('ps.playlist = :id')
            ->setParameter('id', $playlistId)
            ->getQuery()
            ->getSingleScalarResult();
        return (int) $result;
    }
}