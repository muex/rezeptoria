<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\RecipeSlugGenerator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a unique slug to recipes and backfill it from the existing titles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe ADD slug VARCHAR(255) DEFAULT NULL');

        $taken = array_flip(RecipeSlugGenerator::RESERVED);

        foreach ($this->connection->fetchAllAssociative('SELECT id, title FROM recipe ORDER BY id') as $row) {
            $base = RecipeSlugGenerator::baseSlug((string) $row['title']);

            $slug = $base;
            for ($suffix = 2; isset($taken[$slug]); ++$suffix) {
                $slug = $base.'-'.$suffix;
            }
            $taken[$slug] = true;

            $this->addSql('UPDATE recipe SET slug = :slug WHERE id = :id', [
                'slug' => $slug,
                'id' => $row['id'],
            ]);
        }

        $this->addSql('CREATE UNIQUE INDEX UNIQ_DA88B137989D9B62 ON recipe (slug)');
        $this->addSql('ALTER TABLE recipe CHANGE slug slug VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_DA88B137989D9B62 ON recipe');
        $this->addSql('ALTER TABLE recipe DROP slug');
    }
}
