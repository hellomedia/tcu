<?php

namespace App\Command;

use App\Entity\PlayerSeason;
use App\Entity\Season;
use App\Repository\PlayerSeasonRepository;
use App\Repository\SeasonRepository;
use App\Service\Ranking\RankingProposal;
use App\Service\Ranking\RankingUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Récupère les classements des joueurs inscrits à une saison sur mon-classement-tennis.be,
 * à partir de leur numéro d'affiliation.
 *
 * Une fois par saison :
 * 1) --previsional : tant que les nouveaux classements ne sont pas officiels, on prend le classement prévisionnel
 * 2) sans option, quand les classements sont officiels : on prend le classement officiel
 *
 * Sans --apply, rien n'est modifié : la commande affiche ce qui changerait.
 */
#[AsCommand(name: 'app:rankings:fetch', description: 'Récupère les classements des joueurs inscrits à une saison (mon-classement-tennis.be)')]
class FetchRankingsCommand extends Command
{
    public function __construct(
        private SeasonRepository $seasonRepository,
        private PlayerSeasonRepository $playerSeasonRepository,
        private RankingUpdater $updater,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('season', null, InputOption::VALUE_REQUIRED, 'Saison, ex: hiver-2026-2027 (par défaut : la saison courante)')
            ->addOption('previsional', null, InputOption::VALUE_NONE, 'Classement prévisionnel au lieu du classement officiel')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Enregistrer les classements (sinon : aperçu uniquement)')
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Pause entre deux pages, en secondes', '1.5')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $slug = $input->getOption('season');
        $season = $slug ? $this->seasonRepository->findOneBySlug($slug) : $this->seasonRepository->findCurrent();

        if (!$season instanceof Season) {
            $io->error($slug ? sprintf('Saison "%s" introuvable.', $slug) : 'Pas de saison courante.');

            return Command::FAILURE;
        }

        $previsional = $input->getOption('previsional');
        $apply = $input->getOption('apply');
        // on reste discret : jamais moins d'une seconde entre deux pages
        $delay = max(1.0, (float) $input->getOption('delay'));

        $io->title(sprintf('%s - classement %s%s', $season, $previsional ? 'prévisionnel' : 'officiel', $apply ? '' : ' (aperçu)'));

        $registrations = array_filter(
            $this->playerSeasonRepository->findBySeasonIndexedByPlayer($season),
            fn(PlayerSeason $registration) => !$registration->isDismissed(),
        );
        usort($registrations, fn(PlayerSeason $a, PlayerSeason $b) => $a->getPlayer()->getLastname() <=> $b->getPlayer()->getLastname());

        $rows = [];
        $changes = 0;
        $problems = 0;
        $first = true;
        $now = new \DateTimeImmutable();

        foreach ($registrations as $registration) {
            $player = $registration->getPlayer();
            $current = $registration->getRanking()?->value ?? '-';

            // une pause entre deux pages du site
            if ($player->getAffiliationNumber() !== null) {
                if (!$first) {
                    usleep((int) ($delay * 1_000_000));
                }
                $first = false;
            }

            $proposal = $this->updater->propose($registration, $previsional);

            $remark = match ($proposal->status) {
                RankingProposal::CHANGE => 'modifié',
                RankingProposal::CONFIRM => 'confirmé (' . RankingUpdater::getSource($previsional)->getLabel() . ')',
                default => $proposal->remark ?? '',
            };

            if ($proposal->status === RankingProposal::PROBLEM) {
                $problems++;
            } elseif ($proposal->isApplicable()) {
                $changes++;

                if ($apply) {
                    $this->updater->apply($registration, $proposal->ranking, $previsional, $now);
                }
            }

            $rows[] = [$player->getName(), $player->getAffiliationNumber() ?? '-', $proposal->siteName ?? '', $current, $proposal->ranking?->value ?? '', $remark];
        }

        if ($apply) {
            $this->entityManager->flush();
        }

        $io->table(['Joueur', 'N° affiliation', 'Nom sur le site', 'Actuel', 'Récupéré', 'Remarque'], $rows);

        $io->writeln(sprintf(' %d inscription(s), %d classement(s) %s, %d à vérifier.', count($registrations), $changes, $apply ? 'modifié(s)' : 'à modifier', $problems));

        if (!$apply && $changes > 0) {
            $io->note('Aperçu uniquement. Ajouter --apply pour enregistrer.');
        }

        return Command::SUCCESS;
    }
}
