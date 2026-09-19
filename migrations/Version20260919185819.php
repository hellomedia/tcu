<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inscriptions (player_season)
 *
 * - confirmed (bool) devient status : pending / confirmed / dismissed.
 *   Une inscription pré-remplie peut être écartée.
 * - les interclubs se jouent en été, les interfacs en hiver.
 *   Les joueurs marqués "interclubs" (repris de la table player dans Hiver 2025-2026 par la migration précédente)
 *   sont inscrits à la saison "Été 2026", sans classement : le classement d'été n'est pas celui d'hiver.
 */
final class Version20260919185819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inscriptions : statut (à confirmer / confirmée / écartée), interclubs en été (=> Été 2026), interfacs en hiver';
    }

    public function up(Schema $schema): void
    {
        // Schema + data: confirmed => status
        $this->addSql('ALTER TABLE player_season ADD status VARCHAR(255) DEFAULT \'confirmed\' NOT NULL');
        $this->addSql("UPDATE player_season SET status = CASE WHEN confirmed THEN 'confirmed' ELSE 'pending' END");
        $this->addSql('ALTER TABLE player_season DROP confirmed');

        // Data: saison Été 2026, si elle n'a pas déjà été créée dans l'admin
        $this->addSql("
            INSERT INTO season (type, year, starts_on, ends_on, is_current)
            SELECT 'ete', 2026, '2026-04-01', '2026-10-31', false
            WHERE NOT EXISTS (SELECT 1 FROM season WHERE type = 'ete' AND year = 2026)
        ");

        // Data: joueurs interclubs de Hiver 2025-2026 => inscription confirmée pour Été 2026, sans classement
        $this->addSql("
            INSERT INTO player_season (player_id, season_id, interclubs, status)
            SELECT ps.player_id, summer.id, true, 'confirmed'
            FROM player_season ps
            INNER JOIN season winter ON winter.id = ps.season_id AND winter.type = 'hiver' AND winter.year = 2025
            CROSS JOIN season summer
            WHERE summer.type = 'ete' AND summer.year = 2026 AND ps.interclubs = true
            ON CONFLICT (player_id, season_id) DO UPDATE SET interclubs = true
        ");

        // Data: pas d'interclubs en hiver, pas d'interfacs en été
        $this->addSql("UPDATE player_season ps SET interclubs = NULL FROM season s WHERE s.id = ps.season_id AND s.type = 'hiver'");
        $this->addSql("UPDATE player_season ps SET interfacs = NULL FROM season s WHERE s.id = ps.season_id AND s.type = 'ete'");
    }

    public function down(Schema $schema): void
    {
        // Data: les interclubs de Été 2026 retournent dans Hiver 2025-2026.
        // NB: la saison Été 2026 et ses inscriptions sont conservées.
        $this->addSql("
            UPDATE player_season ps
            SET interclubs = true
            FROM season winter, player_season summer_ps
            INNER JOIN season summer ON summer.id = summer_ps.season_id AND summer.type = 'ete' AND summer.year = 2026
            WHERE winter.id = ps.season_id AND winter.type = 'hiver' AND winter.year = 2025
                AND summer_ps.player_id = ps.player_id AND summer_ps.interclubs = true
        ");

        // Schema + data: status => confirmed. Une inscription écartée redevient "à confirmer".
        $this->addSql('ALTER TABLE player_season ADD confirmed BOOLEAN DEFAULT true NOT NULL');
        $this->addSql("UPDATE player_season SET confirmed = (status = 'confirmed')");
        $this->addSql('ALTER TABLE player_season DROP status');
    }
}
