<?php

namespace App\Entity;

use App\Repository\BlogCategoryRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: BlogCategoryRepository::class)]
class BlogCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 120, unique: true)]
    private ?string $slug = null;

    #[ORM\OneToMany(targetEntity: BlogPost::class, mappedBy: 'category')]
    private Collection $posts;

    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static
    {
        $this->name = $name;
        $this->slug = mb_strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', strip_tags($name)), '-'), 'UTF-8');
        return $this;
    }
    public function getSlug(): ?string { return $this->slug; }
    public function getPosts(): Collection { return $this->posts; }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}