<?php

namespace App\Entity;

use App\Repository\SongRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SongRepository::class)]
#[ORM\Table(name: 'song')]
class Song
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 512)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $artist = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $album = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(length: 1024, unique: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: PlaylistSong::class, mappedBy: 'song', cascade: ['remove'])]
    private Collection $playlistSongs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->playlistSongs = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getArtist(): ?string { return $this->artist; }
    public function setArtist(?string $artist): static { $this->artist = $artist; return $this; }

    public function getAlbum(): ?string { return $this->album; }
    public function setAlbum(?string $album): static { $this->album = $album; return $this; }

    public function getDuration(): ?int { return $this->duration; }
    public function setDuration(?int $duration): static { $this->duration = $duration; return $this; }

    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }

    public function getGenre(): ?string { return $this->genre; }
    public function setGenre(?string $genre): static { $this->genre = $genre; return $this; }

    public function getYear(): ?int { return $this->year; }
    public function setYear(?int $year): static { $this->year = $year; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getPlaylistSongs(): Collection { return $this->playlistSongs; }

    public function getFormattedDuration(): string
    {
        if ($this->duration === null) {
            return '--:--';
        }
        return sprintf('%d:%02d', intdiv($this->duration, 60), $this->duration % 60);
    }

    public function getDisplayName(): string
    {
        if ($this->artist) {
            return $this->artist . ' - ' . $this->title;
        }
        return $this->title ?? 'Unknown';
    }
}