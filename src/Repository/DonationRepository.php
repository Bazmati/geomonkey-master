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
}
