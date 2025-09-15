<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250915163655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE slot DROP CONSTRAINT fk_ac0e2067e3184009');
        $this->addSql('ALTER TABLE slot ADD CONSTRAINT FK_AC0E2067E3184009 FOREIGN KEY (court_id) REFERENCES court (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE slot DROP CONSTRAINT FK_AC0E2067E3184009');
        $this->addSql('ALTER TABLE slot ADD CONSTRAINT fk_ac0e2067e3184009 FOREIGN KEY (court_id) REFERENCES court (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
