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

    public function findByFileHash(string $hash): ?Song
    {
        return $this->findOneBy(['fileHash' => $hash]);
    }

    public function findAllOrderedByArtist(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.artist', 'ASC')
            ->addOrderBy('s.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Song[]
     */
    public function findTopPlayed(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.playCount', 'DESC')
            ->addOrderBy('s.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTotalPlays(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.playCount), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalListeningDuration(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.playCount * COALESCE(s.duration, 0)), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTop10ListeningDuration(): int
    {
        $topSongs = $this->findTopPlayed(10);
        $total = 0;
        foreach ($topSongs as $song) {
            $total += $song->getTotalListeningDuration();
        }
        return $total;
    }

    /**
     * @return array<array{artist: string, songCount: int, totalPlays: int, totalDuration: int}>
     */
    public function getTopArtists(int $limit = 10): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('COALESCE(NULLIF(TRIM(s.artist), \'\'), \'Unknown\') AS artist, COUNT(s.id) AS songCount, SUM(s.playCount) AS totalPlays, SUM(s.playCount * COALESCE(s.duration, 0)) AS totalDuration')
            ->groupBy('artist')
            ->orderBy('totalPlays', 'DESC')
            ->addOrderBy('songCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(function ($row) {
            return [
                'artist' => $row['artist'],
                'songCount' => (int) $row['songCount'],
                'totalPlays' => (int) $row['totalPlays'],
                'totalDuration' => (int) $row['totalDuration'],
            ];
        }, $rows);
    }
}