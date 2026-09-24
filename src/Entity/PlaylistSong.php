<?php

namespace App\Entity;

use App\Repository\PlaylistSongRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaylistSongRepository::class)]
#[ORM\Table(name: 'playlist_song')]
#[ORM\UniqueConstraint(name: 'unique_playlist_song', columns: ['playlist_id', 'song_id'])]
class PlaylistSong
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Playlist::class, inversedBy: 'playlistSongs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Playlist $playlist = null;

    #[ORM\ManyToOne(targetEntity: Song::class, inversedBy: 'playlistSongs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Song $song = null;

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int { return $this->id; }

    public function getPlaylist(): ?Playlist { return $this->playlist; }
    public function setPlaylist(?Playlist $playlist): static { $this->playlist = $playlist; return $this; }

    public function getSong(): ?Song { return $this->song; }
    public function setSong(?Song $song): static { $this->song = $song; return $this; }

    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): static { $this->position = $position; return $this; }
}