<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Season.published : interfacs visibles sur le site public. Les saisons qui ont déjà des poules sont publiées ;
 * la saison en préparation (sans poules) affiche une page d'attente jusqu'à sa publication dans l'admin.
 */
final class Version20260925220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Season.published : interfacs publiés sur le site ; les saisons avec des poules sont publiées';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE season ADD is_published BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('UPDATE season SET is_published = true WHERE EXISTS (SELECT 1 FROM "group" g WHERE g.season_id = season.id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE season DROP is_published');
    }
}
