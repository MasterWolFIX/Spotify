<?php

namespace App\Command;

use App\Repository\SongRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:hash-songs', description: 'Backfill real MD5 file hashes for all songs in the library.')]
class HashSongsCommand extends Command
{
    public function __construct(
        private readonly SongRepository $songRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $songs = $this->songRepository->findAll();
        $updated = 0;
        $missing = 0;

        foreach ($songs as $song) {
            $path = $song->getFilePath();
            if (!file_exists($path) || !is_readable($path)) {
                $missing++;
                continue;
            }

            $hash = md5_file($path);
            if ($hash === false) {
                continue;
            }

            // Check for collision with another song (keep first occurrence)
            $existing = $this->songRepository->findByFileHash($hash);
            if ($existing !== null && $existing->getId() !== $song->getId()) {
                $io->warning(sprintf('Duplicate detected: "%s" == "%s" — skipping.', $song->getTitle(), $existing->getTitle()));
                continue;
            }

            if ($song->getFileHash() !== $hash) {
                $song->setFileHash($hash);
                $updated++;
            }
        }

        $this->em->flush();

        $io->success(sprintf('Updated %d songs. %d files not found.', $updated, $missing));
        return Command::SUCCESS;
    }
}
