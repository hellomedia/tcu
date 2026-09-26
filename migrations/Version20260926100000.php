<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Group.displayOrder : ordre d'affichage des poules au sein de leur saison.
 * Les poules existantes sont numérotées par nom (l'ordre affiché jusqu'ici) dans chaque saison.
 */
final class Version20260926100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Group.displayOrder : ordre d\'affichage des poules dans leur saison ; poules existantes numérotées par nom';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" ADD display_order INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE "group" SET display_order = ranked.rank FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY season_id ORDER BY name) AS rank FROM "group") ranked WHERE "group".id = ranked.id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" DROP display_order');
    }
}
