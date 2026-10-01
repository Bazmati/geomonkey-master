<?php

namespace App\Entity;

use App\Enum\ImageVisibility;
use App\Interface\ImageableInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Image de galerie réutilisable partout dans le site.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\GalleryImageRepository')]
#[ORM\Table(name: 'gallery_image')]
class GalleryImage implements ImageableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank(message: "La description alternative (alt) est obligatoire pour l'accessibilité")]
    #[Assert\Length(max: 500)]
    private string $alt = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: ImageVisibility::class)]
    private ImageVisibility $visibility = ImageVisibility::Members;

    #[ORM\Column]
    private bool $rgpdConsent = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $consentDetail = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $consentedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by_id', referencedColumnName: 'id')]
    private ?User $uploadedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'galleryImages')]
    private Collection $events;

    #[ORM\ManyToMany(targetEntity: BlogPost::class, mappedBy: 'galleryImages')]
    private Collection $blogPosts;

    private ?File $imageFile = null;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->blogPosts = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    // ImageableInterface
    public function getId(): ?int { return $this->id; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $file): void { $this->imageFile = $file; if ($file) $this->updatedAt = new \DateTime(); }
    public function getImagePath(): string { return 'gallery'; }
    public function getDefaultImage(): string { return ''; }
    public function hasImage(): bool { return $this->image !== null && $this->image !== ''; }

    // Getters/Setters
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getAlt(): string { return $this->alt; }
    public function setAlt(string $alt): static { $this->alt = $alt; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getVisibility(): ImageVisibility { return $this->visibility; }
    public function setVisibility(ImageVisibility $visibility): static { $this->visibility = $visibility; return $this; }
    public function hasRgpdConsent(): bool { return $this->rgpdConsent; }
    public function setRgpdConsent(bool $rgpdConsent): static { $this->rgpdConsent = $rgpdConsent; return $this; }
    public function getConsentDetail(): ?string { return $this->consentDetail; }
    public function setConsentDetail(?string $consentDetail): static { $this->consentDetail = $consentDetail; return $this; }
    public function getConsentedAt(): ?\DateTimeImmutable { return $this->consentedAt; }
    public function setConsentedAt(?\DateTimeImmutable $consentedAt): static { $this->consentedAt = $consentedAt; return $this; }
    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $uploadedBy): static { $this->uploadedBy = $uploadedBy; return $this; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    public function isPublished(): bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): static { $this->isPublished = $isPublished; return $this; }

    // Relations
    public function getEvents(): Collection { return $this->events; }
    public function addEvent(Event $event): static { if (!$this->events->contains($event)) { $this->events->add($event); $event->addGalleryImage($this); } return $this; }
    public function removeEvent(Event $event): static { if ($this->events->removeElement($event)) { $event->removeGalleryImage($this); } return $this; }
    public function getBlogPosts(): Collection { return $this->blogPosts; }
    public function addBlogPost(BlogPost $blogPost): static { if (!$this->blogPosts->contains($blogPost)) { $this->blogPosts->add($blogPost); $blogPost->addGalleryImage($this); } return $this; }
    public function removeBlogPost(BlogPost $blogPost): static { if ($this->blogPosts->removeElement($blogPost)) { $blogPost->removeGalleryImage($this); } return $this; }

    // Business logic
    /**
     * Vérifie si le consentement RGPD est valide (coché + daté)
     * Une image ne peut être publique que si elle a un consentement valide.
     */
    public function hasValidConsent(): bool
    {
        return $this->rgpdConsent && $this->consentedAt !== null;
    }

    /**
     * Accorde le consentement RGPD et le date automatiquement.
     * Utiliser cette méthode plutôt que setRgpdConsent(true) pour garantir
     * la cohérence entre rgpdConsent et consentedAt.
     */
    public function giveConsent(): void
    {
        $this->rgpdConsent = true;
        $this->consentedAt = new \DateTimeImmutable();
    }

    /**
     * Retire le consentement RGPD et rétrograde automatiquement l'image.
     * Si l'image était publique, elle passe en visibilité Members (conformité RGPD).
     * Utiliser cette méthode plutôt que setRgpdConsent(false) pour garantir
     * la cohérence entre rgpdConsent, consentedAt et visibility.
     */
    public function withdrawConsent(): void
    {
        $this->rgpdConsent = false;
        $this->consentedAt = null;
        $this->consentDetail = null;

        // Rétrogradation auto : une image sans consentement ne peut pas rester publique
        if ($this->visibility === ImageVisibility::Public) {
            $this->visibility = ImageVisibility::Members;
        }
    }

    /**
     * Peut-on rendre cette image publique ? (ancienne méthode canBePublic, conservée pour compatibilité)
     * @deprecated Utiliser hasValidConsent() à la place
     */
    public function canBePublic(): bool
    {
        return $this->hasValidConsent();
    }

    public function isUsed(): bool { return $this->events->count() > 0 || $this->blogPosts->count() > 0; }
    public function getUsageCount(): int { return $this->events->count() + $this->blogPosts->count(); }
}