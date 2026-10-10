<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ParticipantConfirmationInfo : créneau communiqué au joueur lors du dernier email (copie) et date du rappel.
 * Permet de distinguer, pour le prochain email, les matchs nouveaux, déplacés et annulés.
 * Les notifications déjà envoyées sont considérées comme portant sur le créneau actuel du match.
 */
final class Version20261011100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ParticipantConfirmationInfo : créneau communiqué (notified_on, notified_time_range, notified_court) et reminded_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participant_confirmation_info ADD notified_on DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE participant_confirmation_info ADD notified_time_range VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE participant_confirmation_info ADD notified_court VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE participant_confirmation_info ADD reminded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // emails déjà envoyés : le joueur connaît le créneau actuel
        $this->addSql(<<<'SQL'
            UPDATE participant_confirmation_info i
            SET notified_on = d.date,
                notified_time_range = to_char(s.starts_at, 'HH24:MI') || '-' || to_char(s.ends_at, 'HH24:MI'),
                notified_court = c.name
            FROM match_participant p
            JOIN booking b ON b.match_id = p.match_id
            JOIN slot s ON s.id = b.slot_id
            JOIN date d ON d.id = s.date_id
            JOIN court c ON c.id = s.court_id
            WHERE i.participant_id = p.id AND i.is_email_sent = true
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participant_confirmation_info DROP notified_on');
        $this->addSql('ALTER TABLE participant_confirmation_info DROP notified_time_range');
        $this->addSql('ALTER TABLE participant_confirmation_info DROP notified_court');
        $this->addSql('ALTER TABLE participant_confirmation_info DROP reminded_at');
    }
}
