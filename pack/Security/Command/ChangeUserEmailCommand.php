<?php

namespace Pack\Security\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:change-email', description: 'Change the email of a user')]
class ChangeUserEmailCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Ask for email
        $email = $io->ask('Enter the user current email: ');

        if (!$email) {
            $io->error('Email is required');

            return Command::FAILURE;
        }

        // Find the user
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        \assert($user instanceof User);

        if (!$user) {
            $io->error('User not found');

            return Command::FAILURE;
        }

        $newEmail = $io->ask('Enter the new email: ');

        if (!$newEmail) {
            $io->error('New email is required');
            return Command::FAILURE;
        }

        // Hash and set the new password
        $user->setEmail($newEmail);

        // Persist and flush
        $this->entityManager->flush();

        $io->success('Email updated successfully');

        return Command::SUCCESS;
    }
}
