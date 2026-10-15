<?php

namespace App\Repository;

use App\Entity\BlogTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BlogTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogTag::class);
    }

    /** Tags utilisés par au moins un article publié, triés par popularité. */
    public function findUsedTags(): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.posts', 'p')
            ->andWhere('p.isPublished = true')
            ->addSelect('COUNT(p.id) AS HIDDEN nb')
            ->groupBy('t.id')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getResult();
    }
}