<?php

namespace App\Entity;

use App\Enum\SocialPlatform;
use App\Repository\SocialNetworkSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SocialNetworkSettingRepository::class)]
#[ORM\Table(name: 'social_network_setting')]
#[ORM\UniqueConstraint(name: 'uniq_platform', fields: ['platform'])]
class SocialNetworkSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: SocialPlatform::class, unique: true)]
    private SocialPlatform $platform;

    /**
     * Réseau visible/actif pour l'association.
     * Désactivé ⇒ pas de bouton de partage, pas de publication auto.
     */
    #[ORM\Column]
    private bool $enabled = false;

    /**
     * Publication automatique des articles de blog sur ce réseau
     * au moment de la mise en ligne par le président.
     * N'est effectif que si enabled = true ET que la plateforme
     * supporte la publication auto (SocialPlatform::supportsAutoPublish()).
     */
    #[ORM\Column]
    private bool $autoPublish = false;

    /**
     * Identifiant de la cible (ID Page Facebook, UUID Instagram,
     * URN organisation LinkedIn, identifiant X, numéro WhatsApp métier…).
     * NON SENSIBLE : peut être public. Les tokens/secrets restent
     * dans .env.local (SOCIAL_*_TOKEN) — jamais en base.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'identifiant ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $externalId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // =========================================
    // MÉTHODES MÉTIER
    // =========================================

    /**
     * Le réseau est-il réellement opérationnel pour la publication auto ?
     * combine : activé + autoPublish demandé + API disponible + identifiant posé.
     */
    public function isAutoPublishReady(): bool
    {
        return $this->enabled
            && $this->autoPublish
            && $this->platform->supportsAutoPublish()
            && $this->externalId !== null
            && $this->externalId !== '';
    }

    public function __toString(): string
    {
        return $this->platform->label();
    }

    // =========================================
    // GETTERS / SETTERS
    // =========================================

    public function getId(): ?int { return $this->id; }

    public function getPlatform(): SocialPlatform { return $this->platform; }
    public function setPlatform(SocialPlatform $platform): static { $this->platform = $platform; return $this; }

    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): static { $this->enabled = $enabled; return $this; }

    public function isAutoPublish(): bool { return $this->autoPublish; }
    public function setAutoPublish(bool $autoPublish): static { $this->autoPublish = $autoPublish; return $this; }

    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): static { $this->externalId = $externalId; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
}