<?php

namespace App\Controller;

use App\Repository\PlaylistRepository;
use App\Repository\SongRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(SongRepository $songRepo, PlaylistRepository $playlistRepo): Response
    {
        $songs = $songRepo->findAllOrderedByArtist();
        $playlists = $playlistRepo->findByUser($this->getUser());

        return $this->render('main/index.html.twig', [
            'songs' => $songs,
            'playlists' => $playlists,
            'songCount' => count($songs),
        ]);
    }
}