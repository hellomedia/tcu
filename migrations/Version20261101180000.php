<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Entraînement annulé : ses créneaux sont libérés, l'horaire est copié pour rester affiché (barré) aux joueurs.
 * Les entraînements déjà annulés gardent leurs créneaux : leur horaire est copié, puis les réservations supprimées.
 */
final class Version20261101180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Practice : horaire mémorisé à l\'annulation (cancelled_on, cancelled_time_range, cancelled_courts), créneaux libérés';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practice ADD cancelled_on DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE practice ADD cancelled_time_range VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE practice ADD cancelled_courts VARCHAR(100) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE practice p
            SET cancelled_on = x.day,
                cancelled_time_range = x.time_range,
                cancelled_courts = left(x.courts, 100)
            FROM (
                SELECT b.practice_id,
                    min(d.date) AS day,
                    to_char(min(s.starts_at), 'HH24:MI') || '-' || to_char(max(s.ends_at), 'HH24:MI') AS time_range,
                    string_agg(DISTINCT c.name, ' et ' ORDER BY c.name) AS courts
                FROM booking b
                JOIN slot s ON s.id = b.slot_id
                JOIN date d ON d.id = s.date_id
                JOIN court c ON c.id = s.court_id
                GROUP BY b.practice_id
            ) x
            WHERE x.practice_id = p.id AND p.cancelled_at IS NOT NULL
            SQL);
        $this->addSql('DELETE FROM booking WHERE practice_id IN (SELECT id FROM practice WHERE cancelled_at IS NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practice DROP cancelled_on');
        $this->addSql('ALTER TABLE practice DROP cancelled_time_range');
        $this->addSql('ALTER TABLE practice DROP cancelled_courts');
    }
}
