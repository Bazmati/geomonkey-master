<?php

namespace App\Entity;

use App\Interface\ImageableInterface;
use App\Repository\BlogPostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlogPostRepository::class)]
#[ORM\Table(name: 'blog_post')]
#[ORM\HasLifecycleCallbacks]
class BlogPost implements ImageableInterface
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
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le slug ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        message: 'Le slug ne doit contenir que des minuscules, des chiffres et des tirets (ex : mon-premier-article).'
    )]
    private ?string $slug = null;

    /**
     * Résumé de l'article (150 à 160 caractères) utilisé comme meta
     * description pour Google et comme accroche dans les listes.
     */
    #[ORM\Column(length: 165, nullable: true)]
    #[Assert\Length(
        min: 80,
        max: 160,
        minMessage: 'Le résumé doit faire au moins {{ limit }} caractères pour une meta description efficace.',
        maxMessage: 'Le résumé ne peut pas dépasser {{ limit }} caractères (limite de troncature Google).'
    )]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le contenu est obligatoire.')]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    #[Assert\NotNull(message: 'L\'auteur est obligatoire.')]
    private ?User $author = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le nom du fichier image ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $image = null;

    /**
     * Images de la galerie associées à cet article
     */
    #[ORM\ManyToMany(targetEntity: GalleryImage::class, inversedBy: 'blogPosts')]
    #[Assert\Valid]
    private Collection $galleryImages;

    // Image file for upload (not persisted in database)
    #[Assert\File(
        maxSize: '5M',
        maxSizeMessage: 'L\'image ne doit pas dépasser {{ limit }}.',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        mimeTypesMessage: 'Formats autorisés : JPG, PNG, GIF, WebP.'
    )]
    private ?File $imageFile = null;

    #[ORM\ManyToOne(targetEntity: BlogCategory::class, inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BlogCategory $category = null;

    #[ORM\ManyToMany(targetEntity: BlogTag::class, inversedBy: 'posts')]
    #[ORM\JoinTable(name: 'blog_post_tag')]
    private Collection $tags;

    #[ORM\OneToMany(targetEntity: BlogImage::class, mappedBy: 'blogPost', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'sortOrder' => 'ASC'])]
    private Collection $images;

    #[ORM\ManyToMany(targetEntity: Event::class, inversedBy: 'blogPosts')]
    #[ORM\JoinTable(name: 'blog_post_event')]
    private Collection $events;

    public function __construct()
    {
        $this->galleryImages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->tags = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->events = new ArrayCollection();
    }

    // =========================================
    // HOOKS DOCTRINE
    // =========================================

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // =========================================
    // MÉTHODES UTILITAIRES
    // =========================================

    /**
     * Génère le slug à partir du titre.
     * « Notre assemblée générale 2026 » → « notre-assemblee-generale-2026 »
     * Appelée à la création uniquement, pour que les URLs restent stables.
     */
    public function computeSlug(): void
    {
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII; lower', $this->title ?? '');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '') ?? '';
        $this->slug = trim($slug, '-') ?: 'article';
    }

    /**
     * Publie l'article et trace la date de mise en ligne.
     * La dépublication conserve l'historique (publishedAt inchangé).
     */
    public function publish(): static
    {
        if (!$this->isPublished && $this->publishedAt === null) {
            $this->publishedAt = new \DateTimeImmutable();
        }
        $this->isPublished = true;

        return $this;
    }

    public function unpublish(): static
    {
        $this->isPublished = false;

        return $this;
    }

    /**
     * Extrait du contenu pour les listes/apercus et les partages sociaux.
     */
    public function getExcerpt(int $length = 160): string
    {
        $text = trim(strip_tags($this->content ?? ''));
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $excerpt = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($excerpt, ' ');

        return ($lastSpace !== false ? mb_substr($excerpt, 0, $lastSpace) : $excerpt) . '…';
    }

        /**
     * Texte à utiliser dans la balise meta description :
     * le résumé rédigé s'il existe, sinon un extrait du contenu.
     */
    public function getMetaDescription(): string
    {
        $summary = trim($this->summary ?? '');

        return $summary !== '' ? $summary : $this->getExcerpt(155);
    }

    public function __toString(): string
    {
        return $this->title !== null && $this->title !== '' ? $this->title : 'Article sans titre';
    }

    // =========================================
    // ImageableInterface
    // =========================================

    public function getId(): ?int { return $this->id; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $file): void { $this->imageFile = $file; }
    public function getImagePath(): string { return 'blog_posts'; }
    public function getDefaultImage(): string { return ''; }
    public function hasImage(): bool { return $this->image !== null && $this->image !== ''; }

    // =========================================
    // GETTERS / SETTERS
    // =========================================

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(?string $slug): static { $this->slug = $slug; return $this; }

    public function getSummary(): ?string { return $this->summary; }
    public function setSummary(?string $summary): static { $this->summary = $summary; return $this; }

    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

        // Catégorie
    public function getCategory(): ?BlogCategory { return $this->category; }
    public function setCategory(?BlogCategory $category): static
    {
        $this->category = $category;
        return $this;
    }

    // Tags
    public function getTags(): Collection { return $this->tags; }
    public function addTag(BlogTag $tag): static
    {
        if (!$this->tags->contains($tag)) { $this->tags->add($tag); }
        return $this;
    }
    public function removeTag(BlogTag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    // Images positionnées
    public function getImages(): Collection { return $this->images; }
    public function addImage(BlogImage $image): static
    {
        if (!$this->images->contains($image)) {
            $image->setBlogPost($this);
            $this->images->add($image);
        }
        return $this;
    }
    public function removeImage(BlogImage $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getBlogPost() === $this) { $image->setBlogPost(null); }
        }
        return $this;
    }

    /**
     * Images d'une position donnée, dans l'ordre.
     * @return BlogImage[]
     */
    public function getImagesByPosition(\App\Enum\ImagePosition $position): array
    {
        return array_values(array_filter(
            $this->images->toArray(),
            static fn (BlogImage $i) => $i->getPosition() === $position
        ));
    }

    // Événements liés
    public function getEvents(): Collection { return $this->events; }
    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) { $this->events->add($event); }
        return $this;
    }
    public function removeEvent(Event $event): static
    {
        $this->events->removeElement($event);
        return $this;
    }

    public function isPublished(): bool { return $this->isPublished; }

    /**
     * @deprecated Utiliser publish()/unpublish() — la date de publication
     *             doit être tracée, et la mise en ligne est réservée au président.
     */
    public function setIsPublished(bool $isPublished): static
    {
        $isPublished ? $this->publish() : $this->unpublish();

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static { $this->publishedAt = $publishedAt; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    // Gallery images relations
    public function getGalleryImages(): Collection { return $this->galleryImages; }
    public function addGalleryImage(GalleryImage $image): static { if (!$this->galleryImages->contains($image)) { $this->galleryImages->add($image); } return $this; }
    public function removeGalleryImage(GalleryImage $image): static { $this->galleryImages->removeElement($image); return $this; }
}