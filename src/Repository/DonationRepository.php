<?php

namespace App\Repository;

use App\Entity\Donation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Donation>
 */
class DonationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Donation::class);
    }

    /**
     * Total des dons payés pour une année donnée (en centimes)
     * Utile pour le suivi trésorerie
     */
    public function getPaidTotalForYear(int $year): int
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $end = $start->add(new \DateInterval('P1Y'));

        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.amount)')
            ->andWhere('d.paymentStatus = :status')
            ->andWhere('d.createdAt >= :start')
            ->andWhere('d.createdAt < :end')
            ->setParameter('status', Donation::STATUS_PAID)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Total NET (après frais Stripe) des dons payés pour une année (en centimes)
     */
    public function getNetTotalForYear(int $year): int
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $end = $start->add(new \DateInterval('P1Y'));

        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.netAmount)')
            ->andWhere('d.paymentStatus = :status')
            ->andWhere('d.createdAt >= :start')
            ->andWhere('d.createdAt < :end')
            ->setParameter('status', Donation::STATUS_PAID)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Liste des dons payés pour une année (ordre décroissant)
     *
     * @return Donation[]
     */
    public function findPaidForYear(int $year): array
    {
        $start = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $end = $start->add(new \DateInterval('P1Y'));

        return $this->createQueryBuilder('d')
            ->andWhere('d.paymentStatus = :status')
            ->andWhere('d.createdAt >= :start')
            ->andWhere('d.createdAt < :end')
            ->setParameter('status', Donation::STATUS_PAID)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste des dons pour le backoffice, filtrable par statut (ordre décroissant).
     * Limite haute de sécurité : 500 lignes affichées (l'export CSV reste complet).
     *
     * @return Donation[]
     */
    public function findForAdmin(?string $status = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(500);

        if ($status !== null) {
            $qb->andWhere('d.paymentStatus = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre de dons par statut (badges de filtres du backoffice)
     *
     * @return array{pending: int, paid: int, failed: int}
     */
    public function countsByStatus(): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.paymentStatus AS status, COUNT(d.id) AS n')
            ->groupBy('d.paymentStatus')
            ->getQuery()
            ->getResult();

        $counts = [
            Donation::STATUS_PENDING => 0,
            Donation::STATUS_PAID => 0,
            Donation::STATUS_FAILED => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * Total des dons payés, toutes années (en centimes)
     */
    public function getPaidTotal(): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.amount)')
            ->andWhere('d.paymentStatus = :status')
            ->setParameter('status', Donation::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Total NET (après frais Stripe) des dons payés, toutes années (en centimes)
     */
    public function getNetTotal(): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.netAmount)')
            ->andWhere('d.paymentStatus = :status')
            ->setParameter('status', Donation::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }
}
