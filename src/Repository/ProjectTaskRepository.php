<?php

namespace App\Repository;

use App\Entity\ProjectTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProjectTask>
 */
class ProjectTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectTask::class);
    }

    /**
     * Prochaine position disponible pour une tâche d'un projet
     * (les tâches s'ajoutent en fin de colonne).
     */
    public function getNextPosition(int $projectId): int
    {
        $max = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->andWhere('t.project = :projectId')
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $max + 1;
    }
}
