<?php

namespace App\Command;

use App\Entity\InterfacMatch;
use App\Entity\MatchParticipant;
use App\Entity\Player;
use App\Enum\Side;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:migrate:players', description: 'Migrate players to participants')]
class MigrateMatchPlayersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->error('This command was meant as a single use migration command. Do not run again. Exiting.');

        return Command::FAILURE;

        $response = $io->confirm('Do you want to migrate match players into participants ?');

        if (!$response) {
            $io->error('Leaving the command');

            return Command::FAILURE;
        }

        $matchs = $this->entityManager->getRepository(InterfacMatch::class)->findAll();

        if (!$matchs) {
            $io->error('No matchs');

            return Command::FAILURE;
        }

        $participantCount = 0;
        $matchCount = 0;

        foreach ($matchs as $match) {

            assert($match instanceof InterfacMatch);

            $side = Side::A;

            foreach ($match->getPlayers() as $player) {

                \assert($player instanceof Player);

                $this->_createParticipant($match, $player, $side);

                // works for 2 players only
                $side = Side::B;

                $participantCount ++;
            }

            $matchCount ++;
        }

        $this->entityManager->flush();

        $io->success($matchCount . ' matchs handled successfully');
        $io->success($participantCount . ' participants migrated successfully');

        return Command::SUCCESS;
    }

    private function _createParticipant(InterfacMatch $match, Player $player, Side $side)
    {
        $participant = new MatchParticipant();

        $participant->setPlayer($player);
        $participant->setMatch($match);
        $participant->setSide($side);

        $this->entityManager->persist($participant);
    }
}
