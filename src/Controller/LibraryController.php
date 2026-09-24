<?php

namespace App\Controller;

use App\Service\MusicLibraryScanner;
use App\Service\PlaylistFolderImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/library')]
#[IsGranted('ROLE_ADMIN')]
class LibraryController extends AbstractController
{
    public function __construct(
        private readonly MusicLibraryScanner $scanner,
        private readonly PlaylistFolderImporter $importer,
    ) {}

    #[Route('/scan', name: 'app_library_scan', methods: ['POST'])]
    public function scan(): JsonResponse
    {
        try {
            $stats = $this->scanner->scan();
            return $this->json([
                'success' => true,
                'added' => $stats['added'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
                'message' => sprintf(
                    'Scan complete: %d new, %d already indexed, %d errors.',
                    $stats['added'], $stats['skipped'], $stats['errors']
                ),
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Scan failed: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/directories', name: 'app_library_directories', methods: ['GET'])]
    public function directories(): JsonResponse
    {
        return $this->json([
            'directories' => $this->scanner->getSubDirectories(),
            'musicDir' => $this->scanner->getMusicDir(),
        ]);
    }

    #[Route('/scan-folder', name: 'app_library_scan_folder', methods: ['POST'])]
    public function scanFolder(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $folder = $data['folder'] ?? '';
        $playlistName = $data['playlistName'] ?? null;

        if ($folder === '') {
            return $this->json(['error' => 'Folder path is required.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->importer->importFolder($folder, $playlistName, $this->getUser());
            return $this->json([
                'success' => true,
                'playlist' => [
                    'id' => $result['playlist']->getId(),
                    'name' => $result['playlist']->getName(),
                ],
                'added' => $result['added'],
                'skipped' => $result['skipped'],
                'message' => sprintf(
                    'Playlist "%s" created: %d songs added, %d already indexed.',
                    $result['playlist']->getName(), $result['added'], $result['skipped']
                ),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}