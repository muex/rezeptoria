<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recipe sections and ingredients with portion support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe MODIFY text LONGTEXT NULL');
        $this->addSql('ALTER TABLE recipe MODIFY ingredients LONGTEXT NULL');
        $this->addSql('ALTER TABLE recipe ADD base_servings INT NOT NULL DEFAULT 4');

        $this->addSql('CREATE TABLE recipe_section (
            id INT AUTO_INCREMENT NOT NULL,
            recipe_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            preparation LONGTEXT DEFAULT NULL,
            INDEX IDX_recipe_section_recipe_id (recipe_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ingredient (
            id INT AUTO_INCREMENT NOT NULL,
            section_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            amount DOUBLE PRECISION DEFAULT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            INDEX IDX_ingredient_section_id (section_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE recipe_section ADD CONSTRAINT FK_recipe_section_recipe FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_ingredient_section FOREIGN KEY (section_id) REFERENCES recipe_section (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_ingredient_section');
        $this->addSql('ALTER TABLE recipe_section DROP FOREIGN KEY FK_recipe_section_recipe');
        $this->addSql('DROP TABLE ingredient');
        $this->addSql('DROP TABLE recipe_section');
        $this->addSql('ALTER TABLE recipe DROP base_servings');
        $this->addSql('ALTER TABLE recipe MODIFY text LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE recipe MODIFY ingredients LONGTEXT NOT NULL');
    }
}
