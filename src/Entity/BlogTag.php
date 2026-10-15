<?php

namespace App\Entity;

use App\Repository\BlogTagRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: BlogTagRepository::class)]
class BlogTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du tag est obligatoire')]
    #[Assert\Length(max: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 60, unique: true)]
    private ?string $slug = null;

    #[ORM\ManyToMany(targetEntity: BlogPost::class, mappedBy: 'tags')]
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
    public function addPost(BlogPost $post): static
    {
        if (!$this->posts->contains($post)) { $this->posts->add($post); }
        return $this;
    }
    public function removePost(BlogPost $post): static
    {
        $this->posts->removeElement($post);
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}