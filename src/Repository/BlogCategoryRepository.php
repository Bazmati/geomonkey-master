<?php

namespace App\Repository;

use App\Entity\BlogCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogCategory::class);
    }

    /**
     * Catégories utilisées par au moins un article publié,
     * triées par nombre d'articles publiés (décroissant).
     */
    public function findUsedCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.posts', 'p')
            ->andWhere('p.isPublished = true')
            ->addSelect('COUNT(p.id) AS HIDDEN nb')
            ->groupBy('c.id')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getResult();
    }
}