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
class BlogPost implements ImageableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu est obligatoire")]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column]
    private ?bool $isPublished = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    private ?User $author = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * Images de la galerie associées à cet article
     */
    #[ORM\ManyToMany(targetEntity: GalleryImage::class, inversedBy: 'blogPosts')]
    private Collection $galleryImages;

    // Image file for upload (not persisted in database)
    private ?File $imageFile = null;

    public function __construct()
    {
        $this->galleryImages = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    // ImageableInterface methods
    public function getId(): ?int { return $this->id; }
    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }
    public function getImageFile(): ?File { return $this->imageFile; }
    public function setImageFile(?File $file): void { $this->imageFile = $file; }
    public function getImagePath(): string { return 'blog_posts'; }
    public function getDefaultImage(): string { return ''; }
    public function hasImage(): bool { return $this->image !== null && $this->image !== ''; }

    // BlogPost specific methods
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }
    public function isPublished(): ?bool { return $this->isPublished; }
    public function setIsPublished(bool $isPublished): static { $this->isPublished = $isPublished; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }

    // Gallery images relations
    public function getGalleryImages(): Collection { return $this->galleryImages; }
    public function addGalleryImage(GalleryImage $image): static { if (!$this->galleryImages->contains($image)) { $this->galleryImages->add($image); } return $this; }
    public function removeGalleryImage(GalleryImage $image): static { $this->galleryImages->removeElement($image); return $this; }
}