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
     *   événements réservés aux membres ;
     * - filtre optionnel : 'a-venir' (futurs), 'passes' (passés),
     *   'tous' (défaut).
     *
     * @return Event[] Triés par date de début décroissante
     */
    public function findVisibleFor(bool $isActiveMember, string $filter = 'tous'): array
    {
        $now = new \DateTimeImmutable();

        // Requête partielle : événements à venir (>=) ou passés (<), tri imposé
        $fetch = function (string $operator, string $order) use ($isActiveMember, $now): array {
            $qb = $this->createQueryBuilder('e')
                ->andWhere('e.isPublished = true');

            if (!$isActiveMember) {
                $qb->andWhere('e.isMembersOnly = false');
            }

            return $qb
                ->andWhere('e.startDate ' . $operator . ' :now')
                ->setParameter('now', $now)
                ->orderBy('e.startDate', $order)
                ->getQuery()
                ->getResult();
        };

        return match ($filter) {
            'a-venir' => $fetch('>=', 'ASC'),
            'passes' => $fetch('<', 'DESC'),
            // Défaut : les événements à venir d'abord (du plus proche au plus lointain),
            // puis les passés (du plus récent au plus ancien).
            default => array_merge($fetch('>=', 'ASC'), $fetch('<', 'DESC')),
        };
    }

    /**
     * Prochains événements publiés (pour le dashboard admin).
     *
     * @return Event[]
     */
    public function findUpcoming(int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isPublished = true')
            ->andWhere('e.startDate >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('e.startDate', 'ASC')
            ->setMaxResults($limit)
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