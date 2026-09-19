<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Joueur : numéro d'affiliation à la fédération (pour récupérer son classement)
 */
final class Version20260919192959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joueur : numéro d\'affiliation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player ADD affiliation_number VARCHAR(20) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_98197A65AE0BDC5E ON player (affiliation_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_98197A65AE0BDC5E');
        $this->addSql('ALTER TABLE player DROP affiliation_number');
    }
}
