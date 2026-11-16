<?php

namespace App\Entity;

use App\Repository\EventTagRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EventTagRepository::class)]
class EventTag
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

    #[ORM\ManyToMany(targetEntity: Event::class, mappedBy: 'tags')]
    private Collection $events;

    public function __construct()
    {
        $this->events = new ArrayCollection();
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

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
