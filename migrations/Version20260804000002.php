<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns the database with what Doctrine derives from the mapping:
 *
 * - recipe.owner_id never got a foreign key, because Version20250129123958
 *   created no constraints at all.
 * - recipe_section and ingredient carry hand-picked constraint and index names,
 *   so every doctrine:migrations:diff kept proposing the rename.
 *
 * Each step is guarded, so a database that already matches skips it.
 */
final class Version20260804000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the missing recipe.owner_id foreign key and rename constraints to the Doctrine defaults';
    }

    public function up(Schema $schema): void
    {
        if ($this->foreignKeyExists('recipe_section', 'FK_recipe_section_recipe')) {
            $this->addSql('ALTER TABLE recipe_section DROP FOREIGN KEY FK_recipe_section_recipe');
        }
        if (!$this->foreignKeyExists('recipe_section', 'FK_619774AF59D8A214')) {
            $this->addSql('ALTER TABLE recipe_section ADD CONSTRAINT FK_619774AF59D8A214 FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
        }
        if ($this->indexExists('recipe_section', 'IDX_recipe_section_recipe_id')) {
            $this->addSql('ALTER TABLE recipe_section RENAME INDEX IDX_recipe_section_recipe_id TO IDX_619774AF59D8A214');
        }

        if ($this->foreignKeyExists('ingredient', 'FK_ingredient_section')) {
            $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_ingredient_section');
        }
        if (!$this->foreignKeyExists('ingredient', 'FK_6BAF7870D823E37A')) {
            $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF7870D823E37A FOREIGN KEY (section_id) REFERENCES recipe_section (id) ON DELETE CASCADE');
        }
        if ($this->indexExists('ingredient', 'IDX_ingredient_section_id')) {
            $this->addSql('ALTER TABLE ingredient RENAME INDEX IDX_ingredient_section_id TO IDX_6BAF7870D823E37A');
        }

        if (!$this->foreignKeyExists('recipe', 'FK_DA88B1377E3C61F9')) {
            // Without a foreign key so far, owner_id may point at a deleted user.
            // Say so instead of letting MySQL fail with a bare SQLSTATE 1452.
            $orphans = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM recipe r LEFT JOIN user u ON u.id = r.owner_id WHERE u.id IS NULL'
            );
            $this->abortIf(
                $orphans > 0,
                sprintf('%d recipe(s) reference a user that no longer exists. Reassign or delete them before adding the foreign key.', $orphans)
            );

            $this->addSql('ALTER TABLE recipe ADD CONSTRAINT FK_DA88B1377E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->foreignKeyExists('recipe', 'FK_DA88B1377E3C61F9')) {
            $this->addSql('ALTER TABLE recipe DROP FOREIGN KEY FK_DA88B1377E3C61F9');
        }

        if ($this->foreignKeyExists('ingredient', 'FK_6BAF7870D823E37A')) {
            $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_6BAF7870D823E37A');
        }
        if (!$this->foreignKeyExists('ingredient', 'FK_ingredient_section')) {
            $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_ingredient_section FOREIGN KEY (section_id) REFERENCES recipe_section (id) ON DELETE CASCADE');
        }
        if ($this->indexExists('ingredient', 'IDX_6BAF7870D823E37A')) {
            $this->addSql('ALTER TABLE ingredient RENAME INDEX IDX_6BAF7870D823E37A TO IDX_ingredient_section_id');
        }

        if ($this->foreignKeyExists('recipe_section', 'FK_619774AF59D8A214')) {
            $this->addSql('ALTER TABLE recipe_section DROP FOREIGN KEY FK_619774AF59D8A214');
        }
        if (!$this->foreignKeyExists('recipe_section', 'FK_recipe_section_recipe')) {
            $this->addSql('ALTER TABLE recipe_section ADD CONSTRAINT FK_recipe_section_recipe FOREIGN KEY (recipe_id) REFERENCES recipe (id) ON DELETE CASCADE');
        }
        if ($this->indexExists('recipe_section', 'IDX_619774AF59D8A214')) {
            $this->addSql('ALTER TABLE recipe_section RENAME INDEX IDX_619774AF59D8A214 TO IDX_recipe_section_recipe_id');
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );
    }
}
