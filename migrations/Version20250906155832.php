<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250906155832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_identifier_date_and_start');
        $this->addSql('DROP INDEX uniq_identifier_date_and_end');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_DATE_START_COURT ON slot (date_id, starts_at, court_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_DATE_END_COURT ON slot (date_id, ends_at, court_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_DATE_START_COURT');
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_DATE_END_COURT');
        $this->addSql('CREATE UNIQUE INDEX uniq_identifier_date_and_start ON slot (date_id, starts_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_identifier_date_and_end ON slot (date_id, ends_at)');
    }
}
