<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inscriptions : source du classement (manuel, saison précédente, prévisionnel, officiel) et date de récupération
 *
 * Classements existants : "saison précédente" pour les inscriptions pré-remplies encore à confirmer, "manuel" pour les autres.
 */
final class Version20260919221442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inscriptions : source du classement et date de récupération';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_season ADD ranking_source VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE player_season ADD ranking_fetched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        // Data
        $this->addSql("UPDATE player_season SET ranking_source = CASE WHEN status = 'pending' THEN 'previous' ELSE 'manual' END WHERE ranking IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_season DROP ranking_source');
        $this->addSql('ALTER TABLE player_season DROP ranking_fetched_at');
    }
}
