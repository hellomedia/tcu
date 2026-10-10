<?php

namespace Admin\Factory;

use App\Entity\Group;
use App\Entity\InterfacMatch;
use App\Entity\MatchParticipant;
use App\Entity\Player;
use App\Enum\Side;
use Doctrine\ORM\EntityManager;

/**
 * Service to handle the generation of round-robin matches for a group of players.
 */
class MatchFactory
{
    public function __construct(
        private EntityManager $entityManager
    )
    {
    }

    /**
     * Generates all matches for a given group in a round-robin format.
     *
     * @param Group $group The group for which to generate matches.
     * @param bool $isDoubleRoundRobin If true, each pair of players will play twice.
     * @return void
     */
    public function generateGroupMatchs(Group $group, bool $isDoubleRoundRobin = false): void
    {
        // Get the players from the group's collection.
        // It's best to convert the Doctrine Collection to a standard array for indexed access.
        $players = $group->getPlayersByRanking()->toArray();
        $numberOfPlayers = count($players);

        // A round-robin schedule is typically represented as a complete graph.
        // We use nested loops to generate each unique edge (match) in the graph.
        // The outer loop iterates through each player.
        for ($i = 0; $i < $numberOfPlayers; $i++) {
            $player1 = $players[$i];

            // The inner loop iterates through the remaining players to form unique pairs.
            // We start from $i + 1 to avoid duplicating matches (e.g., A vs B and B vs A)
            // and to prevent a player from playing against themselves.
            for ($j = $i + 1; $j < $numberOfPlayers; $j++) {
                $player2 = $players[$j];

                // 1. Create the first match entity.
                $match = new InterfacMatch();
                $group->addMatch($match);

                $participant1 = $this->_createParticipant($match, $player1, Side::A);
                $match->addParticipant($participant1);
                
                $participant2 = $this->_createParticipant($match, $player2, Side::B);
                $match->addParticipant($participant2);

                // Persist the match entity to be saved to the database.
                $this->entityManager->persist($match);

                // 2. Handle the optional double round-robin.
                if ($isDoubleRoundRobin) {
                    $matchReturn = new InterfacMatch();
                    $group->addMatch($matchReturn);
                    // Add players again for the second match. The order doesn't matter
                    // unless you plan on tracking home/away status, for example.
                    $matchReturn->addParticipant($participant1);
                    $matchReturn->addParticipant($participant2);

                    $this->entityManager->persist($matchReturn);
                }
            }
        }

        $this->entityManager->flush();
    }

    public function deleteGroupMatchs(Group $group): void
    {
        foreach ($group->getMatchs() as $match) {
            $this->remove($match);
        }
    }

    public function regenerateGroupMatchs(Group $group): void
    {
        $this->deleteGroupMatchs($group);

        $this->generateGroupMatchs($group);
    }

    /**
     * Add a series of group matchs to a group with existing matches
     */
    public function addGroupMatchs(Group $group): void
    {
        $this->generateGroupMatchs($group);
    }

    /**
     * Create the matchs missing in a group with existing matchs (player added after the generation...).
     * Existing matchs, planned or played, are left untouched. Idempotent.
     *
     * @return int number of matchs created
     */
    public function completeGroupMatchs(Group $group): int
    {
        $created = 0;

        foreach ($group->getMissingPairs() as $pair) {
            for ($i = 0; $i < $pair['missing']; $i++) {
                $match = new InterfacMatch();
                $group->addMatch($match);

                // like generateGroupMatchs() : the better ranked player on side A
                $match->addParticipant($this->_createParticipant($match, $pair['a'], Side::A));
                $match->addParticipant($this->_createParticipant($match, $pair['b'], Side::B));

                $this->entityManager->persist($match);
                $created++;
            }
        }

        $this->entityManager->flush();

        return $created;
    }

    /**
     * Delete the matchs of players who are no longer in the group.
     * Played matchs (with a result) are kept.
     *
     * @return array{deleted: int, kept: int}
     */
    public function deleteOrphanMatchs(Group $group): array
    {
        $deleted = 0;
        $kept = 0;

        foreach ($group->getOrphanMatchs() as $match) {
            if ($match->getResult() !== null) {
                $kept++;
                continue;
            }

            $this->remove($match);
            $deleted++;
        }

        return ['deleted' => $deleted, 'kept' => $kept];
    }

    public function remove(InterfacMatch $match): void
    {
        $booking = $match->getBooking();
        $group = $match->getGroup();

        if ($group) {
            $group->removeMatch($match);
        }

        if ($booking) {
            $booking->getSlot()->setBooking(null);
            $this->entityManager->remove($booking);
        }

        foreach ($match->getParticipants() as $participant) {
            $this->entityManager->remove($participant);
        }

        $this->entityManager->remove($match);

        $this->entityManager->flush();
    }

    private function _createParticipant(InterfacMatch $match, Player $player, Side $side): MatchParticipant
    {
        $participant = new MatchParticipant();

        $participant->setPlayer($player);
        $participant->setMatch($match);
        $participant->setSide($side);

        $this->entityManager->persist($participant);

        return $participant;
    }
}