<?php

namespace App\Entity;

use App\Repository\SongPlayRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SongPlayRepository::class)]
#[ORM\Table(name: 'song_play')]
#[ORM\Index(name: 'idx_song_play_played_at', columns: ['played_at'])]
class SongPlay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Song::class, inversedBy: 'songPlays')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Song $song = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $playedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $duration = 0; // seconds listened

    public function __construct()
    {
        $this->playedAt = new \DateTimeImmutable();
        $this->duration = 0;
    }

    public function getId(): ?int { return $this->id; }

    public function getSong(): ?Song { return $this->song; }
    public function setSong(?Song $song): static { $this->song = $song; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPlayedAt(): ?\DateTimeImmutable { return $this->playedAt; }
    public function setPlayedAt(\DateTimeImmutable $playedAt): static { $this->playedAt = $playedAt; return $this; }

    public function getDuration(): int { return $this->duration; }
    public function setDuration(int $duration): static { $this->duration = $duration; return $this; }
}
