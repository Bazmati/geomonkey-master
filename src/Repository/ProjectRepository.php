<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Projets publiés sur le site public, du plus récent au plus ancien.
     *
     * @return Project[]
     */
    public function findPublished(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->orderBy('p.startDate', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Un projet publié, par slug (le front ne sert JAMAIS un brouillon,
     * même avec le slug deviné).
     */
    public function findOnePublishedBySlug(string $slug): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isPublished = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
    /**
     * Projets en cours (non terminés), les plus récents d'abord.
     *
     * @return Project[]
     */
    public function findOngoing(int $limit = 6): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status != :completed')
            ->setParameter('completed', \App\Enum\ProjectStatus::Completed)
            ->orderBy('p.startDate', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

}
