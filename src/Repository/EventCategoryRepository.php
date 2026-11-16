<?php

namespace App\Repository;

use App\Entity\EventCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventCategory::class);
    }

    /**
     * Catégories utilisées par au moins un événement publié,
     * triées par nombre d'événements publiés (décroissant).
     */
    public function findUsedCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.events', 'e')
            ->andWhere('e.isPublished = true')
            ->addSelect('COUNT(e.id) AS HIDDEN nb')
            ->groupBy('c.id')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
