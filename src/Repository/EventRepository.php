<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Événements visibles sur le site public :
     * - publiés ;
     * - si l'internaute n'est pas membre actif, on exclut les
     *   événements réservés aux membres.
     *
     * @return Event[] Triés par date de début décroissante
     */
    public function findVisibleFor(bool $isActiveMember): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isPublished = true');

        if (!$isActiveMember) {
            $qb->andWhere('e.isMembersOnly = false');
        }

        return $qb
            ->orderBy('e.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Un événement publié, visible selon le statut de membre de l'internaute.
     * Le front ne doit JAMAIS servir un événement non publié, même avec l'ID deviné.
     */
    public function findOneVisibleBy(int $id, bool $isActiveMember): ?Event
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.id = :id')
            ->andWhere('e.isPublished = true')
            ->setParameter('id', $id);

        if (!$isActiveMember) {
            $qb->andWhere('e.isMembersOnly = false');
        }

        return $qb
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Prochains événements visibles (pour la page d'accueil, etc.).
     *
     * @return Event[]
     */
    public function findUpcomingVisibleFor(bool $isActiveMember, int $limit = 3): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isPublished = true')
            ->andWhere('e.endDate >= :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('e.startDate', 'ASC')
            ->setMaxResults($limit);

        if (!$isActiveMember) {
            $qb->andWhere('e.isMembersOnly = false');
        }

        return $qb->getQuery()->getResult();
    }
}