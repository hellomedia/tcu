<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115192841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Match results: points as floats (0.5 point each for an unfinished set without a clear lead), recomputed from the scores';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE match_result ALTER points_a TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE match_result ALTER points_b TYPE DOUBLE PRECISION');

        // same rules as MatchResult::setPoints(), summed over the sets played
        $pointsA = $pointsB = [];
        foreach ([1, 2, 3] as $set) {
            $pointsA[] = self::setPoints("set{$set}_a", "set{$set}_b", true);
            $pointsB[] = self::setPoints("set{$set}_b", "set{$set}_a", false);
        }
        $this->addSql(\sprintf('UPDATE match_result SET points_a = %s, points_b = %s', implode(' + ', $pointsA), implode(' + ', $pointsB)));
    }

    public function down(Schema $schema): void
    {
        // half points rounded up: a tied set gave 1 point each before
        $this->addSql('ALTER TABLE match_result ALTER points_a TYPE SMALLINT USING CEIL(points_a)');
        $this->addSql('ALTER TABLE match_result ALTER points_b TYPE SMALLINT USING CEIL(points_b)');
    }

    /**
     * SQL points of one side for a set (side A leads a tied set, as in MatchResult::setPoints())
     */
    private static function setPoints(string $own, string $other, bool $isA): string
    {
        $x = "COALESCE($own, 0)";
        $y = "COALESCE($other, 0)";
        $leads = $isA ? "$x >= $y" : "$x > $y";

        return "(CASE
            WHEN $own IS NULL AND $other IS NULL THEN 0
            WHEN $leads AND $x >= 6 AND ($x - $y >= 2 OR $x = 7) THEN 2
            WHEN NOT ($leads) AND $y >= 6 AND ($y - $x >= 2 OR $y = 7) THEN CASE WHEN $y = 7 AND $x = 6 THEN 1 ELSE 0 END
            WHEN $leads AND $x - $y >= 2 THEN 1
            WHEN NOT ($leads) AND $y - $x >= 2 THEN 0
            ELSE 0.5
        END)";
    }
}
