<?php

namespace App\Service;

use App\Entity\Playlist;
use App\Entity\PlaylistSong;
use App\Entity\User;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use getID3;

class PlaylistFolderImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SongRepository $songRepository,
        private readonly MusicLibraryScanner $scanner,
        private readonly string $musicDir,
    ) {}

    public function importFolder(string $folderPath, ?string $playlistName = null, ?User $createdBy = null): array
    {
        $fullPath = rtrim($this->musicDir, '/') . '/' . ltrim($folderPath, '/');

        $realFull = realpath($fullPath);
        $realMusic = realpath($this->musicDir);
        if ($realFull === false || $realMusic === false || !str_starts_with($realFull, $realMusic)) {
            throw new \InvalidArgumentException('Invalid folder path.');
        }

        if (!is_dir($realFull)) {
            throw new \InvalidArgumentException('Folder does not exist: ' . $folderPath);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($realFull, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $mp3Files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'mp3') {
                $mp3Files[] = $file->getPathname();
            }
        }
        sort($mp3Files);

        if (empty($mp3Files)) {
            throw new \RuntimeException('No MP3 files found in: ' . $folderPath);
        }

        $playlist = new Playlist();
        $playlist->setName($playlistName ?? basename($realFull));
        $playlist->setCreatedBy($createdBy);
        $this->em->persist($playlist);

        $getID3 = new getID3();
        $stats = ['added' => 0, 'skipped' => 0];
        $position = 0;

        foreach ($mp3Files as $filePath) {
            $song = $this->songRepository->findByFilePath($filePath);
            if ($song === null) {
                $song = $this->scanner->createSongFromFile($filePath, $getID3);
                $this->em->persist($song);
                $stats['added']++;
            } else {
                $stats['skipped']++;
            }

            $alreadyInPlaylist = false;
            foreach ($playlist->getPlaylistSongs() as $ps) {
                if ($ps->getSong() === $song) {
                    $alreadyInPlaylist = true;
                    break;
                }
            }

            if (!$alreadyInPlaylist) {
                $playlistSong = new PlaylistSong();
                $playlistSong->setSong($song);
                $playlistSong->setPosition($position++);
                $playlist->addPlaylistSong($playlistSong);
                $this->em->persist($playlistSong);
            }
        }

        $this->em->flush();

        return [
            'playlist' => $playlist,
            'added' => $stats['added'],
            'skipped' => $stats['skipped'],
        ];
    }
}