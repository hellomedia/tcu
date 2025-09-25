<?php

namespace Pack\Security\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:new', description: 'Add an admin user')]
class AddAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->warning('This will create an admin user');
        
        $email = $io->ask('Enter the email: ');

        if (!$email) {
            $io->error('Email is required');

            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);

        $password = $io->ask('Enter the password: ');

        if (!$password) {
            $io->error('Password is required');

            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $user->addRole('ROLE_ADMIN');

        $user->setVerified(true);
        $user->setEnabled(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Admin user ' . $email . ' created');

        return Command::SUCCESS;
    }
}
