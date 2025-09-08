<?php

namespace Admin\Factory;

use App\Entity\Group;
use App\Entity\InterfacMatch;
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
        $players = $group->getPlayers()->toArray();
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
                // Note: The Match entity's ManyToMany relationship with Player is crucial here.
                // The provided code assumes this has been corrected.
                $match->addPlayer($player1);
                $match->addPlayer($player2);

                // Persist the match entity to be saved to the database.
                $this->entityManager->persist($match);

                // 2. Handle the optional double round-robin.
                if ($isDoubleRoundRobin) {
                    $matchReturn = new InterfacMatch();
                    $group->addMatch($matchReturn);
                    // Add players again for the second match. The order doesn't matter
                    // unless you plan on tracking home/away status, for example.
                    $matchReturn->addPlayer($player1);
                    $matchReturn->addPlayer($player2);
                    $this->entityManager->persist($matchReturn);
                }
            }
        }

        $this->entityManager->flush();
    }

    public function deleteGroupMatchs(Group $group)
    {
        foreach ($group->getMatchs() as $match) {
            
            $group->removeMatch($match);

            $this->entityManager->remove($match);
        }

        $this->entityManager->flush();
    }

    public function regenerateGroupMatchs(Group $group)
    {
        $this->deleteGroupMatchs($group);

        $this->generateGroupMatchs($group);
    }

    /**
     * Add a series of group matchs to a group with existing matches
     */
    public function addGroupMatchs(Group $group)
    {
        $this->generateGroupMatchs($group);
    }
}