<?php

namespace App\Controller;

use App\Entity\Song;
use App\Repository\PlaylistRepository;
use App\Repository\SongPlayRepository;
use App\Repository\SongRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends AbstractController
{
    #[Route('/stats', name: 'app_stats', methods: ['GET'])]
    public function index(
        SongRepository $songRepo,
        SongPlayRepository $songPlayRepo,
        PlaylistRepository $playlistRepo,
        Request $request
    ): Response {
        $top10Songs = $songRepo->findTopPlayed(10);
        $totalPlays = $songRepo->getTotalPlays();
        $totalListeningDuration = $songRepo->getTotalListeningDuration();
        $top10ListeningDuration = $songRepo->getTop10ListeningDuration();
        $allSongsCount = $songRepo->count([]);

        $top10PlaysCount = 0;
        foreach ($top10Songs as $song) {
            $top10PlaysCount += $song->getPlayCount();
        }

        $top10PlaysPercentage = $totalPlays > 0 ? round(($top10PlaysCount / $totalPlays) * 100, 1) : 0.0;
        $top10ListeningPercentage = $totalListeningDuration > 0 ? round(($top10ListeningDuration / $totalListeningDuration) * 100, 1) : 0.0;

        $rawTopArtists = $songRepo->getTopArtists(5);
        $topArtists = array_map(function ($item) {
            $item['formattedDuration'] = $this->formatSeconds($item['totalDuration']);
            return $item;
        }, $rawTopArtists);

        $recentPlays = $songPlayRepo->findRecent(12);
        $playlists = $playlistRepo->findByUser($this->getUser());

        $topSong = !empty($top10Songs) && $top10Songs[0]->getPlayCount() > 0 ? $top10Songs[0] : null;
        $topArtist = !empty($topArtists) && $topArtists[0]['totalPlays'] > 0 ? $topArtists[0] : null;

        if ($request->isXmlHttpRequest() || $request->query->getBoolean('json')) {
            return $this->json([
                'totalPlays' => $totalPlays,
                'totalListeningDuration' => $totalListeningDuration,
                'formattedTotalListeningDuration' => $this->formatSeconds($totalListeningDuration),
                'top10ListeningDuration' => $top10ListeningDuration,
                'formattedTop10ListeningDuration' => $this->formatSeconds($top10ListeningDuration),
                'top10ListeningPercentage' => $top10ListeningPercentage,
                'top10PlaysCount' => $top10PlaysCount,
                'top10PlaysPercentage' => $top10PlaysPercentage,
                'allSongsCount' => $allSongsCount,
                'topArtists' => $topArtists,
                'top10' => array_map(function (Song $song) {
                    return [
                        'id' => $song->getId(),
                        'title' => $song->getTitle(),
                        'artist' => $song->getArtist(),
                        'album' => $song->getAlbum(),
                        'playCount' => $song->getPlayCount(),
                        'duration' => $song->getDuration(),
                        'formattedDuration' => $song->getFormattedDuration(),
                        'totalDuration' => $song->getTotalListeningDuration(),
                        'formattedListeningTime' => $song->getFormattedListeningTime(),
                    ];
                }, $top10Songs),
            ]);
        }

        return $this->render('stats/index.html.twig', [
            'top10Songs' => $top10Songs,
            'totalPlays' => $totalPlays,
            'totalListeningDuration' => $totalListeningDuration,
            'formattedTotalListeningDuration' => $this->formatSeconds($totalListeningDuration),
            'top10ListeningDuration' => $top10ListeningDuration,
            'formattedTop10ListeningDuration' => $this->formatSeconds($top10ListeningDuration),
            'top10ListeningPercentage' => $top10ListeningPercentage,
            'top10PlaysCount' => $top10PlaysCount,
            'top10PlaysPercentage' => $top10PlaysPercentage,
            'topArtists' => $topArtists,
            'recentPlays' => $recentPlays,
            'topSong' => $topSong,
            'topArtist' => $topArtist,
            'allSongsCount' => $allSongsCount,
            'playlists' => $playlists,
        ]);
    }

    private function formatSeconds(int $totalSeconds): string
    {
        if ($totalSeconds <= 0) {
            return '0 min';
        }

        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d godz. %d min', $hours, $minutes);
        }
        if ($minutes > 0) {
            return sprintf('%d min %d s', $minutes, $seconds);
        }
        return sprintf('%d s', $seconds);
    }
}
