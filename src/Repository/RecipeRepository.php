<?php

namespace App\Repository;

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
