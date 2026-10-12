<?php

namespace App\Repository;

use App\Entity\SocialNetworkSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialNetworkSetting>
 */
class SocialNetworkSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialNetworkSetting::class);
    }

    /**
     * Retourne la config d'un réseau (null si non configurée).
     */
    public function findOneByPlatform(string $platform): ?SocialNetworkSetting
    {
        return $this->findOneBy(['platform' => $platform]);
    }

    /**
     * Réseaux activés — pilote les boutons de partage (niveau 1).
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true]);
    }

    /**
     * Réseaux prêts pour la publication automatique (niveau 2).
     */
    public function findAutoPublishReady(): array
    {
        return array_filter(
            $this->findBy(['enabled' => true, 'autoPublish' => true]),
            fn (SocialNetworkSetting $s) => $s->isAutoPublishReady()
        );
    }
}