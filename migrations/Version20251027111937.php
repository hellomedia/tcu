<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251027111937 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_ ADD player_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_ ADD CONSTRAINT FK_265BC90A99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_265BC90A99E6F5DF ON user_ (player_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_ DROP CONSTRAINT FK_265BC90A99E6F5DF');
        $this->addSql('DROP INDEX UNIQ_265BC90A99E6F5DF');
        $this->addSql('ALTER TABLE user_ DROP player_id');
    }
}
