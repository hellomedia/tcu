<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dispos : de Player vers PlayerSeason (contenu existant => inscription Hiver 2025-2026)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player_season ADD availabilities TEXT DEFAULT NULL');

        // Data : les dispos existantes vont à l'inscription Hiver 2025-2026 du joueur.
        // Un joueur avec des dispos mais sans inscription pour cette saison en reçoit une (confirmée), pour ne rien perdre.
        $this->addSql("
            INSERT INTO player_season (player_id, season_id, status)
            SELECT p.id, winter.id, 'confirmed'
            FROM player p
            CROSS JOIN season winter
            WHERE winter.type = 'hiver' AND winter.year = 2025
              AND p.availabilities IS NOT NULL AND p.availabilities <> ''
              AND NOT EXISTS (SELECT 1 FROM player_season ps WHERE ps.player_id = p.id AND ps.season_id = winter.id)
        ");
        $this->addSql("
            UPDATE player_season ps
            SET availabilities = p.availabilities
            FROM player p, season winter
            WHERE ps.player_id = p.id AND ps.season_id = winter.id
              AND winter.type = 'hiver' AND winter.year = 2025
              AND p.availabilities IS NOT NULL AND p.availabilities <> ''
        ");

        $this->addSql('ALTER TABLE player DROP availabilities');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE player ADD availabilities TEXT DEFAULT NULL');
        $this->addSql("
            UPDATE player p
            SET availabilities = ps.availabilities
            FROM player_season ps, season winter
            WHERE ps.player_id = p.id AND ps.season_id = winter.id
              AND winter.type = 'hiver' AND winter.year = 2025
        ");
        $this->addSql('ALTER TABLE player_season DROP availabilities');
    }
}
