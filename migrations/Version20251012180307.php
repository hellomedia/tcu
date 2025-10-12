<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012180307 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE interfac_match_player DROP CONSTRAINT fk_8116b03299e6f5df');
        $this->addSql('ALTER TABLE interfac_match_player DROP CONSTRAINT fk_8116b032121b0c7d');
        $this->addSql('DROP TABLE interfac_match_player');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE interfac_match_player (interfac_match_id INT NOT NULL, player_id INT NOT NULL, PRIMARY KEY (interfac_match_id, player_id))');
        $this->addSql('CREATE INDEX idx_8116b032121b0c7d ON interfac_match_player (interfac_match_id)');
        $this->addSql('CREATE INDEX idx_8116b03299e6f5df ON interfac_match_player (player_id)');
        $this->addSql('ALTER TABLE interfac_match_player ADD CONSTRAINT fk_8116b03299e6f5df FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE interfac_match_player ADD CONSTRAINT fk_8116b032121b0c7d FOREIGN KEY (interfac_match_id) REFERENCES interfac_match (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
