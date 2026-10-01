<?php

namespace App\Repository;

use App\Entity\EventParticipant;
use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Enum\OfficeFunction;
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

    /**
     * Trouve les images visibles pour un utilisateur donné.
     * 
     * Filtrage côté base de données (sécurité) :
     * - Anonyme : uniquement les images Public et publiées
     * - Bureau : toutes les images publiées
     * - Membre : Public + Members + Participants (des événements auxquels il participe)
     * 
     * Note : On utilise 2 requêtes pour plus de lisibilité plutôt qu'une requête complexe.
     * C'est un compromis acceptable pour la maintenance du code.
     * 
     * @param User|null $user L'utilisateur (null pour anonyme)
     * @return GalleryImage[] Images visibles
     */
    public function findVisibleForUser(?User $user): array
    {
        $qb = $this->createQueryBuilder('gi')
            ->andWhere('gi.isPublished = true')
            ->andWhere('gi.image IS NOT NULL')
            ->orderBy('gi.createdAt', 'DESC');

        if ($user === null) {
            // Anonyme : uniquement les images publiques
            return $qb
                ->andWhere('gi.visibility = :public')
                ->setParameter('public', ImageVisibility::Public->value)
                ->getQuery()
                ->getResult();
        }

        $function = $user->getOfficeFunction();
        $isBureau = in_array($function, [
            OfficeFunction::President,
            OfficeFunction::Treasurer,
            OfficeFunction::Secretary,
        ], true);

        if ($isBureau) {
            // Bureau : voit toutes les images publiées
            return $qb->getQuery()->getResult();
        }

        // Pour les membres non-bureau : Public + Members + Participants
        // 1. Récupérer les images Public et Members
        $baseImages = $qb
            ->andWhere('gi.visibility IN (:visibilities)')
            ->setParameter('visibilities', [
                ImageVisibility::Public->value,
                ImageVisibility::Members->value
            ])
            ->getQuery()
            ->getResult();

        // 2. Récupérer les images Participants des événements auxquels l'utilisateur participe
        $participantImages = $this->createQueryBuilder('gi2')
            ->join('gi2.events', 'e')
            ->join(EventParticipant::class, 'ep', 'WITH', 'ep.event = e')
            ->where('ep.user = :user')
            ->andWhere('gi2.visibility = :participants')
            ->andWhere('gi2.isPublished = true')
            ->andWhere('gi2.id NOT IN (:baseImageIds)') // Éviter les doublons
            ->setParameter('user', $user)
            ->setParameter('participants', ImageVisibility::Participants->value)
            ->setParameter('baseImageIds', array_map(fn($img) => $img->getId(), $baseImages))
            ->getQuery()
            ->getResult();

        // Fusionner et dédupliquer
        $allImages = array_merge($baseImages, $participantImages);
        
        // Utiliser un tableau associatif pour dédupliquer par ID
        $uniqueImages = [];
        foreach ($allImages as $image) {
            if ($image->getId() !== null) {
                $uniqueImages[$image->getId()] = $image;
            }
        }

        return array_values($uniqueImages);
    }
}