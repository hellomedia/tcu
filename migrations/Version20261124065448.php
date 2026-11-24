<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124065448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Groups: hidden flag (draft group, visible only in the admin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" ADD hidden BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "group" DROP hidden');
    }
}
