<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Table player_season renommée registration, avec l'entité (App\Entity\Registration, ex-PlayerSeason).
 * Doctrine ne sait pas générer un renommage : migration écrite à la main (table, séquence, contraintes, index).
 */
final class Version20260925210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table player_season renommée registration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_season RENAME TO registration');
        $this->addSql('ALTER SEQUENCE player_season_id_seq RENAME TO registration_id_seq');
        $this->addSql('ALTER TABLE registration RENAME CONSTRAINT player_season_pkey TO registration_pkey');
        $this->addSql('ALTER INDEX player_season_unique RENAME TO registration_unique');
        // noms générés par Doctrine à partir du nom de la table
        $this->addSql('ALTER INDEX idx_6fe4cd7799e6f5df RENAME TO idx_62a8a7a799e6f5df');
        $this->addSql('ALTER INDEX idx_6fe4cd774ec001d1 RENAME TO idx_62a8a7a74ec001d1');
        $this->addSql('ALTER TABLE registration RENAME CONSTRAINT fk_6fe4cd7799e6f5df TO fk_62a8a7a799e6f5df');
        $this->addSql('ALTER TABLE registration RENAME CONSTRAINT fk_6fe4cd774ec001d1 TO fk_62a8a7a74ec001d1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registration RENAME CONSTRAINT fk_62a8a7a74ec001d1 TO fk_6fe4cd774ec001d1');
        $this->addSql('ALTER TABLE registration RENAME CONSTRAINT fk_62a8a7a799e6f5df TO fk_6fe4cd7799e6f5df');
        $this->addSql('ALTER INDEX idx_62a8a7a74ec001d1 RENAME TO idx_6fe4cd774ec001d1');
        $this->addSql('ALTER INDEX idx_62a8a7a799e6f5df RENAME TO idx_6fe4cd7799e6f5df');
        $this->addSql('ALTER INDEX registration_unique RENAME TO player_season_unique');
        $this->addSql('ALTER TABLE registration RENAME CONSTRAINT registration_pkey TO player_season_pkey');
        $this->addSql('ALTER SEQUENCE registration_id_seq RENAME TO player_season_id_seq');
        $this->addSql('ALTER TABLE registration RENAME TO player_season');
    }
}
