<?php

namespace App\Pack\Security\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:change-password', description: 'Change the password of a user')]
class ChangeUserPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Ask for email
        $email = $io->ask('Enter the user email: ');

        if (!$email) {
            $io->error('User is required');

            return Command::FAILURE;
        }

        // Find the user
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error('User not found');

            return Command::FAILURE;
        }

        $newPassword = $io->ask('Enter the new password: ');

        if (!$newPassword) {
            $io->error('Password is required');
            return Command::FAILURE;
        }

        // Hash and set the new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);

        // Persist and flush
        $this->entityManager->flush();

        $io->success('Password updated successfully');

        return Command::SUCCESS;
    }
}
