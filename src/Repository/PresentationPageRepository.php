<?php

namespace App\Repository;

use App\Entity\PresentationPage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PresentationPage>
 */
class PresentationPageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PresentationPage::class);
    }

    /**
     * Pages visibles publiquement, dans l'ordre d'affichage
     * @return PresentationPage[]
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}