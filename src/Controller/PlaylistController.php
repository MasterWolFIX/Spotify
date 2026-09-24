<?php

namespace App\Controller;

use App\Entity\Playlist;
use App\Entity\PlaylistSong;
use App\Repository\PlaylistRepository;
use App\Repository\PlaylistSongRepository;
use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/playlists')]
class PlaylistController extends AbstractController
{
    public function __construct(
        private readonly PlaylistRepository $playlistRepository,
        private readonly PlaylistSongRepository $playlistSongRepository,
        private readonly SongRepository $songRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_playlists_index', methods: ['GET'])]
    public function index(): Response
    {
        $playlists = $this->playlistRepository->findByUser($this->getUser());
        return $this->render('playlists/index.html.twig', [
            'playlists' => $playlists,
        ]);
    }

    #[Route('/{id}', name: 'app_playlists_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Playlist $playlist): Response
    {
        return $this->render('playlists/show.html.twig', [
            'playlist' => $playlist,
        ]);
    }

    #[Route('/data', name: 'app_playlists_data', methods: ['GET'])]
    public function data(): JsonResponse
    {
        $playlists = $this->playlistRepository->findByUser($this->getUser());
        return $this->json(array_map($this->playlistToArray(...), $playlists));
    }

    #[Route('', name: 'app_playlists_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            return $this->json(['error' => 'Playlist name is required.'], Response::HTTP_BAD_REQUEST);
        }

        $playlist = new Playlist();
        $playlist->setName($name);
        $playlist->setCreatedBy($this->getUser());

        $this->em->persist($playlist);
        $this->em->flush();

        return $this->json($this->playlistToArray($playlist), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'app_playlists_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Playlist $playlist): JsonResponse
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $playlist->getCreatedBy() === $user;

        if (!$isAdmin && !$isOwner) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($playlist);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/songs', name: 'app_playlists_add_song', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function addSong(Playlist $playlist, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $playlist->getCreatedBy() === $user;

        if (!$isAdmin && !$isOwner) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $songId = $data['songId'] ?? null;

        if (!$songId) {
            return $this->json(['error' => 'songId is required.'], Response::HTTP_BAD_REQUEST);
        }

        $song = $this->songRepository->find($songId);
        if (!$song) {
            return $this->json(['error' => 'Song not found.'], Response::HTTP_NOT_FOUND);
        }

        foreach ($playlist->getPlaylistSongs() as $ps) {
            if ($ps->getSong()->getId() === $song->getId()) {
                return $this->json(['error' => 'Song already in playlist.'], Response::HTTP_CONFLICT);
            }
        }

        $maxPos = $this->playlistSongRepository->getMaxPosition($playlist->getId());

        $playlistSong = new PlaylistSong();
        $playlistSong->setSong($song);
        $playlistSong->setPosition($maxPos + 1);
        $playlist->addPlaylistSong($playlistSong);

        $this->em->persist($playlistSong);
        $this->em->flush();

        return $this->json(['success' => true, 'position' => $playlistSong->getPosition()], Response::HTTP_CREATED);
    }

    #[Route('/{id}/songs/{songId}', name: 'app_playlists_remove_song', methods: ['DELETE'], requirements: ['id' => '\d+', 'songId' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function removeSong(Playlist $playlist, int $songId): JsonResponse
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $playlist->getCreatedBy() === $user;

        if (!$isAdmin && !$isOwner) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        foreach ($playlist->getPlaylistSongs() as $ps) {
            if ($ps->getSong()->getId() === $songId) {
                $this->em->remove($ps);
                $this->em->flush();
                return $this->json(['success' => true]);
            }
        }

        return $this->json(['error' => 'Song not found in playlist.'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/{id}/reorder', name: 'app_playlists_reorder', methods: ['PUT'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function reorder(Playlist $playlist, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $playlist->getCreatedBy() === $user;

        if (!$isAdmin && !$isOwner) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $order = $data['order'] ?? [];

        $songMap = [];
        foreach ($playlist->getPlaylistSongs() as $ps) {
            $songMap[$ps->getSong()->getId()] = $ps;
        }

        foreach ($order as $position => $songId) {
            if (isset($songMap[$songId])) {
                $songMap[$songId]->setPosition($position);
            }
        }

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/cover', name: 'app_playlists_cover', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function uploadCover(Playlist $playlist, Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $isAdmin = $this->isGranted('ROLE_ADMIN');
            $isOwner = $playlist->getCreatedBy() === $user;

            if (!$isAdmin && !$isOwner) {
                return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
            }

            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $file */
            $file = $request->files->get('cover');
            if ($file) {
                if (!$file->isValid()) {
                    return $this->json(['error' => 'Upload error: ' . $file->getErrorMessage()], Response::HTTP_BAD_REQUEST);
                }

                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/playlists';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $extension = $file->guessExtension();
                if (!$extension) {
                    $extension = $file->getClientOriginalExtension() ?: 'png';
                }
                
                $filename = uniqid() . '.' . $extension;
                $file->move($uploadDir, $filename);
                
                if ($playlist->getImagePath()) {
                    $oldPath = $uploadDir . '/' . $playlist->getImagePath();
                    if (file_exists($oldPath) && is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }

                $playlist->setImagePath($filename);
                $this->em->flush();

                return $this->json(['success' => true, 'imagePath' => $filename]);
            }

            return $this->json(['error' => 'No file uploaded.'], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    private function playlistToArray(Playlist $playlist): array
    {
        return [
            'id' => $playlist->getId(),
            'name' => $playlist->getName(),
            'songCount' => $playlist->getSongCount(),
            'createdAt' => $playlist->getCreatedAt()?->format('Y-m-d'),
            'url' => $this->generateUrl('app_playlists_show', ['id' => $playlist->getId()]),
            'imagePath' => $playlist->getImagePath(),
        ];
    }
}