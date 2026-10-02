<?php

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membership>
 */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /**
     * Trouve la dernière adhésion active d'un utilisateur
     */
    public function findLastActiveByUser(User $user): ?Membership
    {
        return $this->createQueryBuilder('m')
            ->where('m.user = :user')
            ->andWhere('m.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('m.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les adhésions expirées et toujours actives
     */
    public function findExpiredAndActive(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->andWhere('m.expiresAt < :now')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les adhésions qui expirent dans 28-31 jours et pour lesquelles
     * un rappel n'a pas encore été envoyé
     */
    public function findAboutToExpireWithoutReminder(): array
    {
        $now = new \DateTimeImmutable();
        $in30Days = $now->add(new \DateInterval('P30D'));
        $yesterday = $now->sub(new \DateInterval('P1D'));

        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->andWhere('m.expiresAt BETWEEN :now AND :in30Days')
            ->andWhere('m.reminderSentAt IS NULL OR m.reminderSentAt < :yesterday')
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->setParameter('in30Days', $in30Days)
            ->setParameter('yesterday', $yesterday)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Membership[] Returns an array of Membership objects
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

    //    public function findOneBySomeField($value): ?Membership
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
