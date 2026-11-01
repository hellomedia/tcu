<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Entraînement : joueurs liés individuellement, en plus des poules ou à leur place (Practice::$players)
 */
final class Version20261102100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Practice : joueurs liés individuellement (practice_player)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE practice_player (practice_id INT NOT NULL, player_id INT NOT NULL, PRIMARY KEY (practice_id, player_id))');
        $this->addSql('CREATE INDEX IDX_348D4C6DED33821 ON practice_player (practice_id)');
        $this->addSql('CREATE INDEX IDX_348D4C6D99E6F5DF ON practice_player (player_id)');
        $this->addSql('ALTER TABLE practice_player ADD CONSTRAINT FK_348D4C6DED33821 FOREIGN KEY (practice_id) REFERENCES practice (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE practice_player ADD CONSTRAINT FK_348D4C6D99E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practice_player DROP CONSTRAINT FK_348D4C6DED33821');
        $this->addSql('ALTER TABLE practice_player DROP CONSTRAINT FK_348D4C6D99E6F5DF');
        $this->addSql('DROP TABLE practice_player');
    }
}
