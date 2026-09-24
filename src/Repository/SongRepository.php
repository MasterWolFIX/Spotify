<?php

namespace App\Repository;

use App\Entity\Song;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Song>
 */
class SongRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Song::class);
    }

    public function findBySearchTerm(string $term): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.title LIKE :term OR s.artist LIKE :term OR s.album LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('s.artist', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByFilePath(string $filePath): ?Song
    {
        return $this->findOneBy(['filePath' => $filePath]);
    }

    public function findAllOrderedByArtist(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.artist', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}