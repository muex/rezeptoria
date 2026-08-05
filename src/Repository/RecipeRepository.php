<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /**
     * Published recipes for everyone, plus the viewer's own ones — those stay
     * listed even while deactivated so the owner can find and re-publish them.
     *
     * @return Recipe[]
     */
    public function findVisibleFor(?User $viewer): array
    {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.owner', 'o')
            ->addSelect('o')
            // The listing prints the categories of every recipe; without this
            // join each card would fetch its own.
            ->leftJoin('r.categories', 'c')
            ->addSelect('c')
            ->orderBy('r.id', 'ASC');

        $public = 'r.active = true AND r.blockedByAdmin = false AND o.active = true';

        if ($viewer instanceof User) {
            $qb->andWhere($qb->expr()->orX($public, 'r.owner = :viewer'))
                ->setParameter('viewer', $viewer);
        } else {
            $qb->andWhere($public);
        }

        /** @var Recipe[] $recipes */
        $recipes = $qb->getQuery()->getResult();

        return $recipes;
    }

    /**
     * Comment counts for a batch of recipes, keyed by recipe id. Counting via
     * the association would load every comment of every recipe just to size the
     * collection; this is one query for the whole page.
     *
     * @param Recipe[] $recipes
     *
     * @return array<int, int>
     */
    public function countCommentsFor(array $recipes): array
    {
        if ([] === $recipes) {
            return [];
        }

        /** @var list<array{recipeId: int|string, commentCount: int|string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(c.recipe) AS recipeId', 'COUNT(c.id) AS commentCount')
            ->from(Comment::class, 'c')
            ->andWhere('c.recipe IN (:recipes)')
            ->setParameter('recipes', $recipes)
            ->groupBy('c.recipe')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['recipeId']] = (int) $row['commentCount'];
        }

        return $counts;
    }

    /**
     * @return Recipe[]
     */
    public function findAllForAdmin(): array
    {
        /** @var Recipe[] $recipes */
        $recipes = $this->createQueryBuilder('r')
            ->innerJoin('r.owner', 'o')
            ->addSelect('o')
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $recipes;
    }

    public function countBlocked(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.blockedByAdmin = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return Recipe[] Returns an array of Recipe objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Recipe
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
