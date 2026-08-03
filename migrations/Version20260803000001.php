<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activation flags for recipes (owner + admin) and users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe ADD active TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE recipe ADD blocked_by_admin TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE user ADD active TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe DROP active');
        $this->addSql('ALTER TABLE recipe DROP blocked_by_admin');
        $this->addSql('ALTER TABLE user DROP active');
    }
}
