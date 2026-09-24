<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Creates an admin user account.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin e-mail address')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email') ?? $io->ask('E-mail address');
        $password = $input->getOption('password') ?? $io->askHidden('Password');

        if (!$email || !$password) {
            $io->error('E-mail and password are required.');
            return Command::FAILURE;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid e-mail address.');
            return Command::FAILURE;
        }

        if (strlen($password) < 8) {
            $io->error('Password must be at least 8 characters.');
            return Command::FAILURE;
        }

        $existing = $this->userRepository->findOneBy(['email' => $email]);
        if ($existing !== null) {
            $existing->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            $hashed = $this->passwordHasher->hashPassword($existing, $password);
            $existing->setPassword($hashed);
            $this->em->flush();
            $io->success(sprintf('User "%s" updated to admin.', $email));
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $hashed = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashed);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf('Admin user "%s" created successfully.', $email));
        $io->note('You can now log in at /login');

        return Command::SUCCESS;
    }
}