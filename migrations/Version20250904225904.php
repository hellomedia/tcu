<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904225904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE interfac_match DROP CONSTRAINT fk_3b918c5df88398a7');
        $this->addSql('DROP INDEX idx_3b918c5df88398a7');
        $this->addSql('ALTER TABLE interfac_match RENAME COLUMN player_group_id TO group_id');
        $this->addSql('ALTER TABLE interfac_match ADD CONSTRAINT FK_3B918C5DFE54D947 FOREIGN KEY (group_id) REFERENCES "group" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_3B918C5DFE54D947 ON interfac_match (group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_DATE_AND_TIME ON time_slot (date_id, time_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE interfac_match DROP CONSTRAINT FK_3B918C5DFE54D947');
        $this->addSql('DROP INDEX IDX_3B918C5DFE54D947');
        $this->addSql('ALTER TABLE interfac_match RENAME COLUMN group_id TO player_group_id');
        $this->addSql('ALTER TABLE interfac_match ADD CONSTRAINT fk_3b918c5df88398a7 FOREIGN KEY (player_group_id) REFERENCES "group" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_3b918c5df88398a7 ON interfac_match (player_group_id)');
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_DATE_AND_TIME');
    }
}
