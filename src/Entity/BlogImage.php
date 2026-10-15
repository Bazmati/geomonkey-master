<?php

namespace App\Entity;

use App\Enum\ImagePosition;
use App\Repository\BlogImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlogImageRepository::class)]
#[ORM\UniqueConstraint(columns: ['blog_post_id', 'gallery_image_id'])]
class BlogImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BlogPost::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BlogPost $blogPost = null;

    #[ORM\ManyToOne(targetEntity: GalleryImage::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private ?GalleryImage $galleryImage = null;

    #[ORM\Column(enumType: ImagePosition::class)]
    private ImagePosition $position = ImagePosition::Middle;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $caption = null;

    public function getId(): ?int { return $this->id; }
    public function getBlogPost(): ?BlogPost { return $this->blogPost; }
    public function setBlogPost(?BlogPost $blogPost): static { $this->blogPost = $blogPost; return $this; }
    public function getGalleryImage(): ?GalleryImage { return $this->galleryImage; }
    public function setGalleryImage(?GalleryImage $galleryImage): static { $this->galleryImage = $galleryImage; return $this; }
    public function getPosition(): ImagePosition { return $this->position; }
    public function setPosition(ImagePosition $position): static { $this->position = $position; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }
    public function getCaption(): ?string { return $this->caption; }
    public function setCaption(?string $caption): static { $this->caption = $caption; return $this; }
}