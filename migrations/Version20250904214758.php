<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250904214758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE interfac_match DROP CONSTRAINT fk_3b918c5df920b9e9');
        $this->addSql('DROP INDEX uniq_3b918c5df920b9e9');
        $this->addSql('ALTER TABLE interfac_match RENAME COLUMN timeslot_id TO time_slot_id');
        $this->addSql('ALTER TABLE interfac_match ADD CONSTRAINT FK_3B918C5DD62B0FA FOREIGN KEY (time_slot_id) REFERENCES time_slot (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3B918C5DD62B0FA ON interfac_match (time_slot_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE interfac_match DROP CONSTRAINT FK_3B918C5DD62B0FA');
        $this->addSql('DROP INDEX UNIQ_3B918C5DD62B0FA');
        $this->addSql('ALTER TABLE interfac_match RENAME COLUMN time_slot_id TO timeslot_id');
        $this->addSql('ALTER TABLE interfac_match ADD CONSTRAINT fk_3b918c5df920b9e9 FOREIGN KEY (timeslot_id) REFERENCES time_slot (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_3b918c5df920b9e9 ON interfac_match (timeslot_id)');
    }
}
