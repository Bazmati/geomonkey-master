<?php

namespace App\Repository;

use App\Entity\GalleryImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GalleryImage>
 *
 * @method GalleryImage|null find($id, $lockMode = null, $lockVersion = null)
 * @method GalleryImage|null findOneBy(array $criteria, array $orderBy = null)
 * @method GalleryImage[]    findAll()
 * @method GalleryImage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GalleryImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryImage::class);
    }

    /**
     * Trouve les images publiques
     */
    public function findPublicImages(): array
    {
        return $this->findBy([
            'visibility' => 'public',
            'isPublished' => true
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Trouve les images visibles par les membres
     */
    public function findMemberImages(): array
    {
        return $this->findBy([
            'visibility' => ['public', 'membres'],
            'isPublished' => true
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Trouve les images non publiées
     */
    public function findUnpublishedImages(): array
    {
        return $this->findBy(['isPublished' => false], ['createdAt' => 'DESC']);
    }

    /**
     * Trouve les images sans consentement RGPD
     */
    public function findImagesWithoutRgpdConsent(): array
    {
        return $this->findBy(['rgpdConsent' => false]);
    }

    /**
     * Trouve les images ornelines (non utilisées)
     */
    public function findOrphanImages(): array
    {
        $qb = $this->createQueryBuilder('gi');
        return $qb
            ->leftJoin('gi.events', 'e')
            ->leftJoin('gi.blogPosts', 'bp')
            ->where('e.id IS NULL AND bp.id IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre d'utilisations d'une image
     */
    public function countImageUsage(GalleryImage $image): int
    {
        $qb = $this->createQueryBuilder('gi');
        return $qb
            ->select('COUNT(e.id) + COUNT(bp.id) as total')
            ->leftJoin('gi.events', 'e')
            ->leftJoin('gi.blogPosts', 'bp')
            ->where('gi.id = :imageId')
            ->setParameter('imageId', $image->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}