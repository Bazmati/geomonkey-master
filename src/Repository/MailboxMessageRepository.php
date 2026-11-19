<?php

namespace App\Repository;

use App\Entity\MailboxMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Lecture du cache local de la boîte mail (rempli par app\:mailbox\:sync).
 *
 * Toutes les requêtes de la page Messages partent d'ici : PostgreSQL
 * répond en quelques millisecondes, l'IMAP ne sert qu'à la synchro
 * et à la lecture d'un message précis.
 *
 * @extends ServiceEntityRepository<MailboxMessage>
 */
class MailboxMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailboxMessage::class);
    }

    /**
     * Page de messages en cache (les plus récents d'abord), filtrable.
     *
     * $search porte sur l'objet ET l'expéditeur (nom ou email) :
     * impossible à faire efficacement côté IMAP, trivial en SQL.
     *
     * Note : le comptage n'utilise PAS d'ORDER BY — PostgreSQL refuse
     * un tri sur une colonne non agrégée dans une requête COUNT.
     *
     * @return array{items: list<MailboxMessage>, total: int}
     */
    public function findPage(int $page, int $perPage, string $search = '', bool $unreadOnly = false): array
    {
        $page = max(1, $page);

        // 1. Comptage (sans tri : COUNT + ORDER BY = erreur PostgreSQL)
        $countQb = $this->createQueryBuilder('m');
        $this->applyFilters($countQb, $search, $unreadOnly);
        $total = (int) $countQb
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // 2. Page (avec tri)
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.sentAt', 'DESC')
            ->addOrderBy('m.uid', 'DESC');
        $this->applyFilters($qb, $search, $unreadOnly);

        /** @var list<MailboxMessage> $items */
        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Filtres communs comptage/page : recherche objet+expéditeur, non lus.
     */
    private function applyFilters(QueryBuilder $qb, string $search, bool $unreadOnly): void
    {
        $search = trim($search);
        if ($search !== '') {
            $needle = '%' . mb_strtolower($search) . '%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(m.subject) LIKE \:q',
                    'LOWER(m.fromName) LIKE :q',
                    'LOWER(m.fromEmail) LIKE \:q'
                )
            )->setParameter('q', $needle);
        }

        if ($unreadOnly) {
            $qb->andWhere('m.seen = false');
        }
    }

    /**
     * Un message par son uid IMAP (ou null).
     */
    public function findOneByUid(int $uid): ?MailboxMessage
    {
        return $this->findOneBy(['uid' => $uid]);
    }

    /**
     * Ensemble des uids déjà en cache (pour l'incrémental de la synchro).
     *
     * @param list<int> $uids
     *
     * @return list<int>
     */
    public function findExistingUids(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('m.uid')
            ->andWhere('m.uid IN (\:uids)')
            ->setParameter('uids', $uids)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $rows);
    }

    /**
     * Marque un message (uid) comme lu dans le cache — appelé par la page
     * de détail qui, elle, marque \Seen sur le serveur IMAP.
     */
    public function markSeenByUid(int $uid): void
    {
        $this->createQueryBuilder('m')
            ->update(MailboxMessage::class, 'm')
            ->set('m.seen', 'true')
            ->andWhere('m.uid = :uid')
            ->setParameter('uid', $uid)
            ->getQuery()
            ->execute();
    }

    /**
     * Date de la dernière synchronisation (pour l'entête de la page).
     */
    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('m')
            ->select('MAX(m.syncedAt)')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? new \DateTimeImmutable($result) : null;
    }

    /**
     * Nombre de messages non lus (badge éventuel de navigation).
     */
    public function countUnread(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.seen = false')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
