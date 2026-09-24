<?php

namespace App\Service;

use App\Entity\Song;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use getID3;
use getid3_lib;

class MusicLibraryScanner
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SongRepository $songRepository,
        private readonly string $musicDir,
    ) {}

    public function scan(): array
    {
        $stats = ['added' => 0, 'skipped' => 0, 'errors' => 0];

        if (!is_dir($this->musicDir)) {
            return $stats;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->musicDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $getID3 = new getID3();

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'mp3') {
                continue;
            }

            $filePath = $file->getPathname();

            if ($this->songRepository->findByFilePath($filePath) !== null) {
                $stats['skipped']++;
                continue;
            }

            try {
                $song = $this->createSongFromFile($filePath, $getID3);
                $this->em->persist($song);
                $stats['added']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
            }
        }

        if ($stats['added'] > 0) {
            $this->em->flush();
        }

        return $stats;
    }

    public function scanDirectory(string $subDir): array
    {
        $fullPath = rtrim($this->musicDir, '/') . '/' . ltrim($subDir, '/');

        if (!str_starts_with(realpath($fullPath) ?: '', realpath($this->musicDir))) {
            throw new \InvalidArgumentException('Katalog znajduje się poza biblioteką muzyczną.');
        }

        return $this->scan();
    }

    public function createSongFromFile(string $filePath, ?getID3 $getID3 = null, ?string $originalTitleFallback = null): Song
    {
        $getID3 ??= new getID3();
        $info = $getID3->analyze($filePath);
        getid3_lib::CopyTagsToComments($info);

        $song = new Song();
        $song->setFilePath($filePath);

        $title = $info['comments']['title'][0] ?? null;
        if (!$title) {
            if ($originalTitleFallback) {
                $title = $originalTitleFallback;
            } else {
                $title = pathinfo($filePath, PATHINFO_FILENAME);
                // Strip uniqid hex suffix like _67cf63a12b4e5
                $title = preg_replace('/_[a-f0-9]{13,}$/i', '', $title);
                $title = str_replace(['_', '-'], ' ', $title);
                $title = preg_replace('/^\d+[\s._-]+/', '', $title);
                $title = trim($title);
            }
        }
        $song->setTitle($title ?: 'Nieznany utwór');

        $song->setArtist($info['comments']['artist'][0] ?? null);
        $song->setAlbum($info['comments']['album'][0] ?? null);
        $song->setGenre($info['comments']['genre'][0] ?? null);

        $yearRaw = $info['comments']['year'][0] ?? $info['comments']['date'][0] ?? null;
        if ($yearRaw) {
            $year = (int) substr((string) $yearRaw, 0, 4);
            if ($year > 1900 && $year <= (int) date('Y') + 1) {
                $song->setYear($year);
            }
        }

        if (isset($info['playtime_seconds'])) {
            $song->setDuration((int) round($info['playtime_seconds']));
        }

        return $song;
    }

    public function getSubDirectories(): array
    {
        if (!is_dir($this->musicDir)) {
            return [];
        }

        $dirs = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->musicDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $relativePath = str_replace($this->musicDir, '', $item->getPathname());
                $dirs[] = ltrim(str_replace('\\', '/', $relativePath), '/');
            }
        }

        sort($dirs);
        return $dirs;
    }

    public function getMusicDir(): string
    {
        return $this->musicDir;
    }
}