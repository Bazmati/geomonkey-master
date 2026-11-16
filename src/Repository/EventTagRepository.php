<?php

namespace App\Repository;

use App\Entity\EventTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventTag::class);
    }

    /** Tags utilisés par au moins un événement publié, triés par popularité. */
    public function findUsedTags(): array
    {
        return $this->createQueryBuilder('t')
            ->innerJoin('t.events', 'e')
            ->andWhere('e.isPublished = true')
            ->addSelect('COUNT(e.id) AS HIDDEN nb')
            ->groupBy('t.id')
            ->orderBy('nb', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
