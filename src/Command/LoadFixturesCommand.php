<?php

namespace App\Command;

use App\Entity\Booking;
use App\Entity\Court;
use App\Entity\Date;
use App\Entity\Group;
use App\Entity\InterfacMatch;
use App\Entity\MatchParticipant;
use App\Entity\MatchResult;
use App\Entity\Player;
use App\Entity\PlayerSeason;
use App\Entity\Season;
use App\Entity\Slot;
use App\Entity\User;
use App\Enum\AccountLanguage;
use App\Enum\BookingType;
use App\Enum\Gender;
use App\Enum\Ranking;
use App\Enum\RankingSource;
use App\Enum\RegistrationStatus;
use App\Enum\SeasonType;
use App\Enum\Side;
use App\Service\RegistrationPrefiller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démo pour le développement. VIDE LA BASE DE DONNÉES.
 *
 * Trois saisons, calculées à partir de la date du jour pour que les fixtures restent utilisables :
 * - le dernier hiver terminé : archive complète (poules, matchs joués, résultats)
 * - l'été : inscriptions uniquement (nouveaux classements)
 *   Les classements des saisons passées sont "officiels", ceux de l'hiver à venir sont repris de la saison précédente.
 *   Pas de numéro d'affiliation : ce sont ceux de vraies personnes.
 * - l'hiver en cours ou à venir : inscriptions pré-remplies (une partie reste à confirmer, une est écartée),
 *   poules, matchs programmés sur les prochains samedis, matchs non programmés
 *
 * La saison courante est celle dans laquelle tombe la date du jour.
 *
 * NB: pas de doctrine-fixtures-bundle, une simple commande suffit.
 */
#[AsCommand(name: 'app:fixtures:load', description: 'DEV : vide la base de données et charge des données de démo')]
class LoadFixturesCommand extends Command
{
    private const PASSWORD = 'password';

    // [prénom, nom, genre, classement hiver terminé, classement été, interfacs, cours, interclubs]
    private const PLAYERS = [
        ['Novak', 'Djokovic', Gender::MEN, Ranking::B_M15_2, Ranking::B_M15_4, true, false, true],
        ['Rafa', 'Nadal', Gender::MEN, Ranking::B_M15_4, Ranking::B_M15_4, true, false, true],
        ['Roger', 'Federer', Gender::MEN, Ranking::B_M15_4, Ranking::B_M15_2, true, false, true],
        ['Justine', 'Henin', Gender::WOMEN, Ranking::B_M15, Ranking::B_M15, true, true, true],
        ['Kim', 'Clijsters', Gender::WOMEN, Ranking::B_M4, Ranking::B_M15, true, false, true],
        ['David', 'Goffin', Gender::MEN, Ranking::B_M2, Ranking::B_M4, true, false, true],
        ['Elise', 'Mertens', Gender::WOMEN, Ranking::B_0, Ranking::B_0, true, true, false],
        ['Andy', 'Murray', Gender::MEN, Ranking::B_2, Ranking::B_0, true, false, false],
        ['Stan', 'Wawrinka', Gender::MEN, Ranking::B_4, Ranking::B_4, true, false, false],
        ['Steffi', 'Graf', Gender::WOMEN, Ranking::C_15, Ranking::B_4, true, true, false],
        ['Andre', 'Agassi', Gender::MEN, Ranking::C_15_1, Ranking::C_15, true, false, false],
        ['Martina', 'Hingis', Gender::WOMEN, Ranking::C_15_4, Ranking::C_15_4, true, true, false],
        ['Björn', 'Borg', Gender::MEN, Ranking::C_30, Ranking::C_15_5, false, true, false],
        ['Serena', 'Williams', Gender::WOMEN, Ranking::C_30_2, Ranking::C_30_1, false, true, true],
        ['Venus', 'Williams', Gender::WOMEN, Ranking::C_30_4, Ranking::C_30_4, false, true, false],
        ['Pete', 'Sampras', Gender::MEN, Ranking::NC, Ranking::C_30_6, false, true, false],
    ];

    // nouveaux joueurs pour l'hiver en cours / à venir
    private const NEW_PLAYERS = [
        ['Carlos', 'Alcaraz', Gender::MEN, Ranking::B_M15_1],
        ['Iga', 'Swiatek', Gender::WOMEN, Ranking::B_M4],
    ];

    /** @var array<string, Date> */
    private array $dates = [];

    /** @var Court[] */
    private array $courts = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private RegistrationPrefiller $prefiller,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ne pas demander de confirmation')
            ->addOption('keep-users', null, InputOption::VALUE_NONE, 'Garder les utilisateurs existants (ils sont détachés de leur joueur)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('Les fixtures vident la base de données : uniquement en environnement dev ou test.');

            return Command::FAILURE;
        }

        $database = $this->entityManager->getConnection()->getDatabase();
        $keepUsers = $input->getOption('keep-users');

        $io->warning(sprintf('Toutes les données de la base "%s" vont être supprimées%s.', $database, $keepUsers ? ' (sauf les utilisateurs)' : ''));

        if (!$input->getOption('force') && !$io->confirm('Continuer ?', false)) {
            return Command::SUCCESS;
        }

        mt_srand(2026); // données identiques à chaque chargement

        $this->purge($keepUsers);

        $this->courts = $this->loadCourts();
        [$pastWinter, $summer, $winter] = $this->loadSeasons();
        $players = $this->loadPlayers($pastWinter, $summer);
        $this->entityManager->flush();

        // Hiver terminé : tout est joué
        $pastGroups = $this->loadGroups($pastWinter, $players);
        $this->entityManager->flush();
        $this->loadPlayedMatchs($pastGroups, $this->saturdays($pastWinter->getStartsOn(), $pastWinter->getEndsOn()));

        // Hiver en cours / à venir
        $this->loadWinterRegistrations($winter);
        $groups = $this->loadGroups($winter, $this->findInterfacsPlayers($winter));
        $this->entityManager->flush();
        $this->loadCurrentMatchs($groups, $winter);

        if (!$keepUsers) {
            $this->loadUsers($players);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Fixtures chargées : %s, %s, %s.', $pastWinter, $summer, $winter));
        if (!$keepUsers) {
            $io->writeln(sprintf(' Utilisateurs (mot de passe "%s") : admin@tcu.test, manager@tcu.test, editor@tcu.test, joueur@tcu.test', self::PASSWORD));
        }

        return Command::SUCCESS;
    }

    private function purge(bool $keepUsers): void
    {
        $connection = $this->entityManager->getConnection();

        // NB: pas de TRUNCATE ... CASCADE : user_ référence player, les utilisateurs seraient supprimés
        // même avec --keep-users. Toutes les tables liées sont listées dans la même commande.
        $tables = [
            'participant_confirmation_info', 'match_result', 'match_participant', 'booking', 'interfac_match',
            'slot', 'date', 'court', 'group_player', '"group"', 'player_season', 'season',
        ];

        if ($keepUsers) {
            $connection->executeStatement('TRUNCATE ' . implode(', ', $tables) . ' RESTART IDENTITY');
            // player est référencé par user_ : TRUNCATE impossible sans vider user_
            $connection->executeStatement('UPDATE user_ SET player_id = NULL');
            $connection->executeStatement('DELETE FROM player');
            $connection->executeStatement('ALTER TABLE player ALTER COLUMN id RESTART');
        } else {
            $tables = [...$tables, 'player', 'reset_password_request', 'user_'];
            $connection->executeStatement('TRUNCATE ' . implode(', ', $tables) . ' RESTART IDENTITY');
        }
    }

    /**
     * @return Court[]
     */
    private function loadCourts(): array
    {
        $courts = [];
        foreach (['BG 1' => 'Blanc Gravier 1', 'BG 2' => 'Blanc Gravier 2', 'French 1' => null, 'BP 1' => null] as $name => $description) {
            $court = (new Court())->setName($name)->setDescription($description);
            $this->entityManager->persist($court);
            $courts[] = $court;
        }

        return $courts;
    }

    /**
     * @return array{Season, Season, Season} hiver terminé, été, hiver en cours ou à venir
     */
    private function loadSeasons(): array
    {
        $today = new \DateTimeImmutable('today');
        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');

        // année de début de l'hiver en cours ou à venir
        $winterYear = $month <= 3 ? $year - 1 : $year;

        $pastWinter = new Season(SeasonType::WINTER, $winterYear - 1);
        $summer = new Season(SeasonType::SUMMER, $winterYear);
        $winter = new Season(SeasonType::WINTER, $winterYear);

        // saison courante : l'hiver s'il a commencé, sinon l'été
        ($today >= $winter->getStartsOn() ? $winter : $summer)->setCurrent(true);

        foreach ([$pastWinter, $summer, $winter] as $season) {
            $this->entityManager->persist($season);
        }

        return [$pastWinter, $summer, $winter];
    }

    /**
     * @return Player[]
     */
    private function loadPlayers(Season $pastWinter, Season $summer): array
    {
        $players = [];

        foreach (self::PLAYERS as $i => [$firstname, $lastname, $gender, $winterRanking, $summerRanking, $interfacs, $cours, $interclubs]) {
            $player = (new Player())
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setGender($gender)
                ->setPhone(sprintf('04%02d 12 34 %02d', 70 + $i, $i))
            ;

            // Hiver : interfacs. Eté : interclubs. Cours toute l'année.
            $player->addSeason((new PlayerSeason())
                ->setSeason($pastWinter)
                ->setRankingFrom($winterRanking, RankingSource::OFFICIAL, $pastWinter->getStartsOn())
                ->setInterfacs($interfacs)
                ->setCours($cours)
                ->setAvailabilities($i % 3 === 0 ? 'Pas dispo avant 16h' : null)
            );
            $player->addSeason((new PlayerSeason())
                ->setSeason($summer)
                ->setRankingFrom($summerRanking, RankingSource::OFFICIAL, $summer->getStartsOn())
                ->setCours($cours)
                ->setInterclubs($interclubs)
            );

            $this->entityManager->persist($player);
            $players[] = $player;
        }

        return $players;
    }

    /**
     * Inscriptions pré-remplies à partir des saisons précédentes (activités de l'hiver passé, classement de l'été),
     * confirmées en partie seulement + 2 nouveaux joueurs
     */
    private function loadWinterRegistrations(Season $winter): void
    {
        $this->prefiller->prefill($winter);

        $registrations = $this->entityManager->getRepository(PlayerSeason::class)->findBy(['season' => $winter], ['id' => 'ASC']);

        foreach ($registrations as $i => $registration) {
            // 1 inscription sur 4 reste à confirmer, la dernière est écartée
            $registration->setStatus(match (true) {
                $i === count($registrations) - 1 => RegistrationStatus::DISMISSED,
                $i % 4 === 3 => RegistrationStatus::PENDING,
                default => RegistrationStatus::CONFIRMED,
            });
        }

        foreach (self::NEW_PLAYERS as [$firstname, $lastname, $gender, $ranking]) {
            $player = (new Player())
                ->setFirstname($firstname)
                ->setLastname($lastname)
                ->setGender($gender)
            ;
            $player->addSeason((new PlayerSeason())
                ->setSeason($winter)
                ->setRanking($ranking)
                ->setInterfacs(true)
            );

            $this->entityManager->persist($player);
        }

        $this->entityManager->flush();
    }

    /**
     * @return Player[] inscrits aux interfacs (inscription confirmée), du meilleur classement au moins bon
     */
    private function findInterfacsPlayers(Season $season): array
    {
        $registrations = $this->entityManager->getRepository(PlayerSeason::class)->findBy(
            ['season' => $season, 'interfacs' => true, 'status' => RegistrationStatus::CONFIRMED],
            ['rankingOrder' => 'DESC'],
        );

        return array_map(fn(PlayerSeason $registration) => $registration->getPlayer(), $registrations);
    }

    /**
     * 2 poules par niveau
     *
     * @param Player[] $players du meilleur classement au moins bon
     *
     * @return Group[]
     */
    private function loadGroups(Season $season, array $players): array
    {
        $players = array_values(array_filter($players, fn(Player $player) => $player->getRegistration($season)?->isInterfacs()));
        $half = (int) ceil(count($players) / 2);

        $groups = [];
        foreach ([array_slice($players, 0, $half), array_slice($players, $half)] as $i => $groupPlayers) {
            $group = (new Group())
                ->setName('Poule ' . ($i + 1))
                ->setSeason($season);

            foreach ($groupPlayers as $player) {
                $group->addPlayer($player);
            }

            $this->entityManager->persist($group);
            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Saison terminée : tous les matchs sont programmés et joués
     *
     * @param Group[] $groups
     * @param \DateTimeImmutable[] $days
     */
    private function loadPlayedMatchs(array $groups, array $days): void
    {
        $slots = $this->createSlots($days);

        foreach ($groups as $group) {
            foreach ($this->createMatchs($group) as $match) {
                $this->book($match, $slots);
                $this->addResult($match);
            }
        }
    }

    /**
     * Saison en cours / à venir :
     * - matchs joués sur les samedis passés de la saison (s'il y en a)
     * - matchs programmés sur les prochains samedis
     * - le reste n'est pas programmé
     *
     * @param Group[] $groups
     */
    private function loadCurrentMatchs(array $groups, Season $season): void
    {
        $today = new \DateTimeImmutable('today');

        $pastDays = $today > $season->getStartsOn() ? $this->saturdays($season->getStartsOn(), $today->modify('-1 day')) : [];
        $pastDays = array_slice($pastDays, -4);
        $futureDays = array_slice($this->saturdays(max($today, $season->getStartsOn()), $season->getEndsOn()), 0, 8);

        // 2 terrains, 1 samedi sur 2 sur les dates futures : il reste des créneaux libres
        $pastSlots = $this->createSlots($pastDays);
        $futureSlots = $this->createSlots($futureDays);

        foreach ($groups as $group) {
            foreach ($this->createMatchs($group) as $i => $match) {
                if ($i % 3 === 0 && $pastSlots) {
                    $this->book($match, $pastSlots);
                    $this->addResult($match);
                } elseif ($i % 3 === 1 && $futureSlots) {
                    $this->book($match, $futureSlots);
                }
                // sinon : match non programmé
            }
        }
    }

    /**
     * @return InterfacMatch[] tous les matchs de la poule (cf Admin\Factory\MatchFactory)
     */
    private function createMatchs(Group $group): array
    {
        $players = $group->getPlayersByRanking()->toArray();
        $matchs = [];

        for ($i = 0; $i < count($players); $i++) {
            for ($j = $i + 1; $j < count($players); $j++) {
                $match = new InterfacMatch();
                $group->addMatch($match);
                $match->addParticipant((new MatchParticipant())->setPlayer($players[$i])->setSide(Side::A));
                $match->addParticipant((new MatchParticipant())->setPlayer($players[$j])->setSide(Side::B));

                $this->entityManager->persist($match);
                $matchs[] = $match;
            }
        }

        // pour ne pas programmer tous les matchs d'un même joueur le même jour
        shuffle($matchs);

        return $matchs;
    }

    /**
     * Premier créneau libre où aucun des joueurs ne joue déjà ce jour-là
     *
     * @param Slot[] $slots
     */
    private function book(InterfacMatch $match, array &$slots): void
    {
        static $busy = []; // [Y-m-d][spl_object_id du joueur]

        foreach ($slots as $key => $slot) {
            $day = $slot->getDate()->getDate()->format('Y-m-d');

            foreach ($match->getPlayers() as $player) {
                if (isset($busy[$day][spl_object_id($player)])) {
                    continue 2;
                }
            }

            $booking = (new Booking())->setType(BookingType::MATCH)->setSlot($slot);
            $slot->setBooking($booking);
            $match->setBooking($booking);
            $this->entityManager->persist($booking);

            foreach ($match->getPlayers() as $player) {
                $busy[$day][spl_object_id($player)] = true;
            }

            unset($slots[$key]);

            return;
        }
        // plus de créneau libre : le match reste non programmé
    }

    /**
     * Le joueur le mieux classé gagne le plus souvent. Vainqueur : 2 points, perdant : 1 point.
     */
    private function addResult(InterfacMatch $match): void
    {
        if (!$match->isScheduled()) {
            return;
        }

        $season = $match->getSeason();
        $playerA = $match->getPlayersForSide(Side::A)[0];
        $playerB = $match->getPlayersForSide(Side::B)[0];

        $aIsFavorite = ($playerA->getRankingOrder($season) ?? 0) >= ($playerB->getRankingOrder($season) ?? 0);
        $favoriteWins = mt_rand(1, 100) <= 75;
        $aWins = $aIsFavorite === $favoriteWins;

        $loserGames = fn() => mt_rand(0, 4);
        $threeSets = mt_rand(1, 100) <= 30;

        // sets du point de vue du vainqueur : [jeux vainqueur, jeux perdant]
        $sets = $threeSets
            ? [[6, $loserGames()], [mt_rand(2, 4), 6], [6, $loserGames()]]
            : [[6, $loserGames()], [6, $loserGames()]];

        $result = new MatchResult();
        foreach ($sets as $i => [$winner, $loser]) {
            $result->{'setSet' . ($i + 1) . 'A'}($aWins ? $winner : $loser);
            $result->{'setSet' . ($i + 1) . 'B'}($aWins ? $loser : $winner);
        }
        $result->setPointsA($aWins ? 2 : 1);
        $result->setPointsB($aWins ? 1 : 2);

        $match->setResult($result);
    }

    /**
     * Samedis de 14h à 18h sur 2 terrains
     *
     * @param \DateTimeImmutable[] $days
     *
     * @return Slot[]
     */
    private function createSlots(array $days): array
    {
        $slots = [];

        foreach ($days as $day) {
            $ymd = $day->format('Y-m-d');

            if (!isset($this->dates[$ymd])) {
                $this->dates[$ymd] = (new Date())->setDate($day);
                $this->entityManager->persist($this->dates[$ymd]);
            }

            for ($hour = 14; $hour < 18; $hour++) {
                foreach (array_slice($this->courts, 0, 2) as $court) {
                    $slot = (new Slot())
                        ->setStartsAt($day->setTime($hour, 0))
                        ->setEndsAt($day->setTime($hour + 1, 0))
                        ->setCourt($court);

                    $this->dates[$ymd]->addSlot($slot);
                    $this->entityManager->persist($slot);
                    $slots[] = $slot;
                }
            }
        }

        return $slots;
    }

    /**
     * @return \DateTimeImmutable[]
     */
    private function saturdays(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $days = [];
        $day = $from->format('N') === '6' ? $from : $from->modify('next saturday');

        for (; $day <= $to; $day = $day->modify('+1 week')) {
            $days[] = $day;
        }

        return $days;
    }

    /**
     * @param Player[] $players
     */
    private function loadUsers(array $players): void
    {
        $users = [
            ['admin@tcu.test', 'Admin', ['ROLE_SUPER_ADMIN'], null],
            ['manager@tcu.test', 'Manager', ['ROLE_ADMIN', 'ROLE_MANAGER'], null],
            ['editor@tcu.test', 'Editor', ['ROLE_ADMIN', 'ROLE_EDITOR'], null],
            // joueur inscrit aux interfacs des 2 hivers
            ['joueur@tcu.test', null, [], $players[4]],
        ];

        foreach ($users as [$email, $name, $roles, $player]) {
            $user = (new User())
                ->setEmail($email)
                ->setName($name ?? $player->getName())
                ->setRoles($roles)
                ->setVerified(true)
                ->setEnabled(true)
                ->setAccountLanguage(AccountLanguage::FRENCH)
                ->setPlayer($player)
            ;
            $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));

            $this->entityManager->persist($user);
        }
    }
}
