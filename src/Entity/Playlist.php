<?php

namespace App\Entity;

use App\Repository\PlaylistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlaylistRepository::class)]
#[ORM\Table(name: 'playlist')]
class Playlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\OneToMany(targetEntity: PlaylistSong::class, mappedBy: 'playlist', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $playlistSongs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->playlistSongs = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $user): static { $this->createdBy = $user; return $this; }

    public function getPlaylistSongs(): Collection { return $this->playlistSongs; }

    public function getSongs(): Collection
    {
        return $this->playlistSongs->map(fn(PlaylistSong $ps) => $ps->getSong());
    }

    public function getSongCount(): int { return $this->playlistSongs->count(); }

    public function getTotalDuration(): int
    {
        return $this->playlistSongs->reduce(
            fn(int $carry, PlaylistSong $ps) => $carry + ($ps->getSong()?->getDuration() ?? 0),
            0
        );
    }

    public function addPlaylistSong(PlaylistSong $playlistSong): static
    {
        if (!$this->playlistSongs->contains($playlistSong)) {
            $this->playlistSongs->add($playlistSong);
            $playlistSong->setPlaylist($this);
        }
        return $this;
    }

    public function removePlaylistSong(PlaylistSong $playlistSong): static
    {
        $this->playlistSongs->removeElement($playlistSong);
        return $this;
    }
}