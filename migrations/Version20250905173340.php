<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250905173340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT fk_e00cedde59e5119c');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE59E5119C FOREIGN KEY (slot_id) REFERENCES slot (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE slot DROP CONSTRAINT fk_ac0e2067b897366b');
        $this->addSql('ALTER TABLE slot ADD CONSTRAINT FK_AC0E2067B897366B FOREIGN KEY (date_id) REFERENCES date (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP CONSTRAINT FK_E00CEDDE59E5119C');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT fk_e00cedde59e5119c FOREIGN KEY (slot_id) REFERENCES slot (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE slot DROP CONSTRAINT FK_AC0E2067B897366B');
        $this->addSql('ALTER TABLE slot ADD CONSTRAINT fk_ac0e2067b897366b FOREIGN KEY (date_id) REFERENCES date (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
