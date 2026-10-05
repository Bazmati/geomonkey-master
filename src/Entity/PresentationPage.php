<?php

namespace App\Entity;

use App\Repository\PresentationPageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PresentationPageRepository::class)]
#[ORM\Table(name: 'presentation_page')]
class PresentationPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $title = '';

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    #[Assert\Length(
        max: 10000,
        maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $content = '';

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La position est obligatoire.')]
    #[Assert\Positive(message: 'La position doit être un nombre positif.')]
    private int $position = 1;

    #[ORM\Column]
    private bool $isActive = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: GalleryImage::class)]
    #[ORM\JoinColumn(name: 'gallery_image_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?GalleryImage $galleryImage = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- GETTERS / SETTERS ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getGalleryImage(): ?GalleryImage
    {
        return $this->galleryImage;
    }

    public function setGalleryImage(?GalleryImage $galleryImage): static
    {
        $this->galleryImage = $galleryImage;
        return $this;
    }

    // --- MÉTHODES UTILITAIRES ---

    /**
     * Génère le slug à partir du titre.
     * « Qui sommes-nous ? » → « qui-sommes-nous »
     * Appelée à la création uniquement, pour que les URLs restent stables.
     */
    public function computeSlug(): void
    {
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII; lower', $this->title ?? '');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '') ?? '';
        $this->slug = trim($slug, '-') ?: 'page';
    }

    public function __toString(): string
    {
        return $this->title !== '' ? $this->title : 'Page sans titre';
    }
}