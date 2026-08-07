<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260807000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the recipe gallery images and one image per recipe section';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recipe_image (id INT AUTO_INCREMENT NOT NULL, recipe_id INT NOT NULL, filename VARCHAR(255) NOT NULL, caption VARCHAR(255) DEFAULT NULL, position INT DEFAULT 0 NOT NULL, INDEX IDX_D32ED04059D8A214 (recipe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE recipe_image ADD CONSTRAINT FK_D32ED04059D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE recipe_section ADD image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_image DROP FOREIGN KEY FK_D32ED04059D8A214');
        $this->addSql('DROP TABLE recipe_image');
        $this->addSql('ALTER TABLE recipe_section DROP image');
    }
}
