<?php

namespace App\Repository;

use App\Entity\MembershipFee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MembershipFee>
 */
class MembershipFeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MembershipFee::class);
    }

    /**
     * Trouve le tarif actuel (celui avec effectiveFrom <= maintenant, le plus récent)
     */
    public function findCurrentFee(): ?MembershipFee
    {
        return $this->createQueryBuilder('f')
            ->where('f.effectiveFrom IS NULL OR f.effectiveFrom <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('f.effectiveFrom', 'DESC')
            ->addOrderBy('f.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le montant actuel en centimes
     * @throws \RuntimeException Si aucun tarif n'est défini
     */
    public function getCurrentAmount(): int
    {
        $fee = $this->findCurrentFee();
        
        if (!$fee) {
            throw new \RuntimeException('Aucun tarif d\'adhésion défini en base de données.');
        }
        
        return $fee->getAmount();
    }

    //    /**
    //     * @return MembershipFee[] Returns an array of MembershipFee objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?MembershipFee
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
