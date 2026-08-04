<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catches the migration chain up with the entities: categories, comments and
 * the recipe columns "ingredients" and "teaser_image" only ever reached the
 * databases through doctrine:schema:update, so no migration created them.
 * Version20260403000001 then modified "ingredients" and failed on any database
 * that had been built from the migrations alone.
 *
 * Versioned before that migration so a fresh database gets the columns in time,
 * and every step is guarded so databases that already have these objects (the
 * ones that were kept up to date by hand) skip it without erroring.
 */
final class Version20260402000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill the schema for categories, comments and the recipe ingredients/teaser image columns';
    }

    public function up(Schema $schema): void
    {
        if (!$this->columnExists('recipe', 'ingredients')) {
            $this->addSql('ALTER TABLE recipe ADD ingredients LONGTEXT DEFAULT NULL');
        }

        if (!$this->columnExists('recipe', 'teaser_image')) {
            $this->addSql('ALTER TABLE recipe ADD teaser_image VARCHAR(255) DEFAULT NULL');
        }

        if (!$this->tableExists('category')) {
            $this->addSql('CREATE TABLE category (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        if (!$this->tableExists('recipe_category')) {
            $this->addSql('CREATE TABLE recipe_category (
                recipe_id INT NOT NULL,
                category_id INT NOT NULL,
                INDEX IDX_70DCBC5F59D8A214 (recipe_id),
                INDEX IDX_70DCBC5F12469DE2 (category_id),
                PRIMARY KEY(recipe_id, category_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE recipe_category ADD CONSTRAINT FK_70DCBC5F59D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE recipe_category ADD CONSTRAINT FK_70DCBC5F12469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        }

        if (!$this->tableExists('comment')) {
            $this->addSql('CREATE TABLE comment (
                id INT AUTO_INCREMENT NOT NULL,
                created_user_id INT DEFAULT NULL,
                recipe_id INT DEFAULT NULL,
                text LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_9474526CE104C1D3 (created_user_id),
                INDEX IDX_9474526C59D8A214 (recipe_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CE104C1D3 FOREIGN KEY (created_user_id) REFERENCES user (id)');
            $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526C59D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('comment')) {
            $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CE104C1D3');
            $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526C59D8A214');
            $this->addSql('DROP TABLE comment');
        }

        if ($this->tableExists('recipe_category')) {
            $this->addSql('ALTER TABLE recipe_category DROP FOREIGN KEY FK_70DCBC5F59D8A214');
            $this->addSql('ALTER TABLE recipe_category DROP FOREIGN KEY FK_70DCBC5F12469DE2');
            $this->addSql('DROP TABLE recipe_category');
        }

        if ($this->tableExists('category')) {
            $this->addSql('DROP TABLE category');
        }

        if ($this->columnExists('recipe', 'teaser_image')) {
            $this->addSql('ALTER TABLE recipe DROP teaser_image');
        }

        if ($this->columnExists('recipe', 'ingredients')) {
            $this->addSql('ALTER TABLE recipe DROP ingredients');
        }
    }

    private function tableExists(string $table): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        );
    }
}
