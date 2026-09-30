<?php

namespace App\Controller;

use App\Entity\Song;
use App\Entity\SongPlay;
use App\Entity\User;
use App\Repository\SongRepository;
use App\Service\MusicLibraryScanner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/songs')]
class SongController extends AbstractController
{
    public function __construct(
        private readonly SongRepository $songRepository,
        private readonly EntityManagerInterface $em,
        private readonly MusicLibraryScanner $scanner,
    ) {}

    #[Route('', name: 'app_songs_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->getString('q', '');

        if ($search !== '') {
            $songs = $this->songRepository->findBySearchTerm($search);
        } else {
            $songs = $this->songRepository->findAllOrderedByArtist();
        }

        if ($request->isXmlHttpRequest() || $request->query->getBoolean('json')) {
            return $this->json(array_map($this->songToArray(...), $songs));
        }

        return $this->render('main/index.html.twig', [
            'songs'     => $songs,
            'songCount' => count($songs),
            'search'    => $search,
        ]);
    }

    #[Route('/{id}', name: 'app_songs_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Song $song): JsonResponse
    {
        return $this->json($this->songToArray($song));
    }

    #[Route('/{id}/stream', name: 'app_songs_stream', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function streamAudio(Song $song): Response
    {
        $filePath = $song->getFilePath();

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw $this->createNotFoundException('Audio file not found.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->headers->set('Content-Type', 'audio/mpeg');
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Cache-Control', 'public, max-age=3600');

        return $response;
    }

    #[Route('', name: 'app_songs_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Wymagane zalogowanie jako Administrator.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('mp3');

        if (!$file) {
            return $this->json(['error' => 'Nie wybrano pliku MP3.'], Response::HTTP_BAD_REQUEST);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'mp3') {
            return $this->json(['error' => 'Dozwolone są tylko pliki MP3.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($file->getSize() > 256 * 1024 * 1024) {
            return $this->json(['error' => 'Plik przekracza limit 256MB.'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $musicDir = $this->scanner->getMusicDir();
        if (!is_dir($musicDir)) {
            mkdir($musicDir, 0775, true);
        }
        $targetDir = $musicDir . '/Uploads';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $fileName = $safeName . '_' . uniqid() . '.mp3';
        $targetPath = $targetDir . '/' . $fileName;

        try {
            $file->move($targetDir, $fileName);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Błąd zapisu pliku na serwerze: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($this->songRepository->findByFilePath($targetPath) !== null) {
            return $this->json(['error' => 'Plik o tej ścieżce już istnieje w bibliotece.'], Response::HTTP_CONFLICT);
        }

        try {
            $song = $this->scanner->createSongFromFile($targetPath, null, $originalName);
            $this->em->persist($song);
            $this->em->flush();
        } catch (\Throwable $e) {
            if (file_exists($targetPath)) {
                unlink($targetPath);
            }
            return $this->json(['error' => 'Błąd przetwarzania pliku audio: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'song' => $this->songToArray($song),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_songs_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Song $song, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Brak uprawnień.'], Response::HTTP_FORBIDDEN);
        }

        $deleteFile = $request->query->getBoolean('deleteFile', true);

        if ($deleteFile && file_exists($song->getFilePath())) {
            unlink($song->getFilePath());
        }

        $this->em->remove($song);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/play', name: 'app_songs_record_play', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function recordPlay(Song $song, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $duration = isset($data['duration']) ? (int) $data['duration'] : ($song->getDuration() ?? 0);

        $song->incrementPlayCount();

        $songPlay = new SongPlay();
        $songPlay->setSong($song);
        $user = $this->getUser();
        if ($user instanceof User) {
            $songPlay->setUser($user);
        }
        $songPlay->setDuration(max(0, $duration));

        $this->em->persist($songPlay);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'songId' => $song->getId(),
            'playCount' => $song->getPlayCount(),
            'formattedListeningTime' => $song->getFormattedListeningTime(),
        ]);
    }

    private function songToArray(Song $song): array
    {
        return [
            'id' => $song->getId(),
            'title' => $song->getTitle(),
            'artist' => $song->getArtist(),
            'album' => $song->getAlbum(),
            'duration' => $song->getDuration(),
            'formattedDuration' => $song->getFormattedDuration(),
            'genre' => $song->getGenre(),
            'year' => $song->getYear(),
            'playCount' => $song->getPlayCount(),
            'formattedListeningTime' => $song->getFormattedListeningTime(),
            'streamUrl' => $this->generateUrl('app_songs_stream', ['id' => $song->getId()]),
            'createdAt' => $song->getCreatedAt()?->format('Y-m-d'),
        ];
    }
}