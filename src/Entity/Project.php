<?php

namespace App\Entity;

use App\Interface\ImageableInterface;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Projet associatif : chantier, création, aventure de long terme.
 * Rattaché aux événements et articles de blog qui le documentent.
 */
#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project implements ImageableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le titre doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Length(max: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: 'La description doit faire au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $startDate = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $endDate = null;

    #[ORM\Column]
    private ?bool $isPublished = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * Événements rattachés à ce projet (propriétaire de la relation).
     */
    #[ORM\ManyToMany(targetEntity: Event::class, inversedBy: 'projects')]
    private Collection $events;

    /**
     * Articles de blog rattachés à ce projet (propriétaire de la relation).
     */
    #[ORM\ManyToMany(targetEntity: BlogPost::class)]
    private Collection $blogPosts;

    // Image file for upload (not persisted in database)
    private ?File $imageFile = null;

    public function __construct()
    {
        $this->events = new ArrayCollection();
        $this->blogPosts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
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

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function isPublished(): ?bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    /**
     * Génère le slug à partir du titre (pattern BlogPost).
     * Appelée à la création uniquement, pour que les URLs restent stables.
     */
    public function computeSlug(): void
    {
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII; lower', $this->title ?? '');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug ?? '') ?? '';
        $this->slug = trim($slug, '-') ?: 'projet';
    }

    // =========================================
    // IMAGE (ImageableInterface)
    // =========================================

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $file): void
    {
        $this->imageFile = $file;
    }

    public function getImagePath(): string
    {
        return 'projects';
    }

    public function getDefaultImage(): string
    {
        return '';
    }

    public function hasImage(): bool
    {
        return $this->image !== null && $this->image !== '';
    }

    // =========================================
    // RELATIONS
    // =========================================

    /** @return Collection<int, Event> */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
        }

        return $this;
    }

    public function removeEvent(Event $event): static
    {
        $this->events->removeElement($event);

        return $this;
    }

    /** @return Collection<int, BlogPost> */
    public function getBlogPosts(): Collection
    {
        return $this->blogPosts;
    }

    public function addBlogPost(BlogPost $blogPost): static
    {
        if (!$this->blogPosts->contains($blogPost)) {
            $this->blogPosts->add($blogPost);
        }

        return $this;
    }

    public function removeBlogPost(BlogPost $blogPost): static
    {
        $this->blogPosts->removeElement($blogPost);

        return $this;
    }
}
