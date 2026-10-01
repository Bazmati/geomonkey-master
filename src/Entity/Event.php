<?php

namespace App\Entity;

use App\Enum\ParticipantRole;
use App\Interface\ImageableInterface;
use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event implements ImageableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: "Le titre doit faire au moins {{ limit }} caractères",
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères"
    )]
    #[Assert\Type(type: 'string', message: "Le titre doit être une chaîne de caractères")]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est obligatoire")]
    #[Assert\Length(
        min: 10,
        max: 5000,
        minMessage: "La description doit faire au moins {{ limit }} caractères",
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de début est obligatoire")]
    // #[Assert\DateTime(message: "Format de date invalide")]
    private ?\DateTime $startDate = null;

    #[ORM\Column]
    #[Assert\NotNull(message: "La date de fin est obligatoire")]
    // #[Assert\DateTime(message: "Format de date invalide")]
    #[Assert\Expression(
        "this.getStartDate() === null or this.getEndDate() >= this.getStartDate()",
        message: "La date de fin doit être postérieure à la date de début"
    )]
    private ?\DateTime $endDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le lieu est obligatoire")]
    #[Assert\Length(max: 255, maxMessage: "Le lieu ne peut pas dépasser {{ limit }} caractères")]
    private ?string $location = null;

    #[ORM\Column]
    private ?bool $isPublished = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

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

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;

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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    // Image file for upload (not persisted in database)
    private ?File $imageFile = null;

    /**
     * Images de la galerie associées à cet événement
     */
    #[ORM\ManyToMany(targetEntity: GalleryImage::class, inversedBy: 'events')]
    private Collection $galleryImages;

    /**
     * Participants à cet événement
     */
    #[ORM\OneToMany(targetEntity: EventParticipant::class, mappedBy: 'event', cascade: ['persist'], orphanRemoval: true)]
    private Collection $participants;

    public function __construct()
    {
        $this->galleryImages = new ArrayCollection();
        $this->participants = new ArrayCollection();
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
        return 'events';
    }

    public function getDefaultImage(): string
    {
        return '';
    }

    public function hasImage(): bool
    {
        return $this->image !== null && $this->image !== '';
    }

    // Gallery images relations
    public function getGalleryImages(): Collection
    {
        return $this->galleryImages;
    }

    public function addGalleryImage(GalleryImage $image): static
    {
        if (!$this->galleryImages->contains($image)) {
            $this->galleryImages->add($image);
        }
        return $this;
    }

    public function removeGalleryImage(GalleryImage $image): static
    {
        $this->galleryImages->removeElement($image);
        return $this;
    }

    // Participants relation methods
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    /**
     * Ajoute un participant à cet événement.
     * 
     * Comportement :
     * - Si l'utilisateur est déjà participant, retourne le participant existant
     * - Si un rôle est fourni ($role !== null), met à jour le rôle existant
     * - Si aucun rôle n'est fourni, préserve le rôle existant (pas de rétrogradation)
     * - Sinon, crée un nouveau participant avec rôle = Participant (par défaut)
     * 
     * La contrainte d'unicité (event_id, user_id) en base garantit qu'il n'y a jamais
     * de doublons, mais cette vérification applicative évite un flush inutile.
     */
    public function addParticipant(User $user, ?ParticipantRole $role = null): EventParticipant
    {
        // Vérifier si l'utilisateur est déjà participant à cet événement
        foreach ($this->participants as $existing) {
            if ($existing->getUser() === $user) {
                // Si un rôle est fourni, mettre à jour le rôle existant
                // Sinon, conserver le rôle actuel (pas de modification silencieuse)
                if ($role !== null) {
                    $existing->setRole($role);
                }
                return $existing;
            }
        }

        // Créer un nouveau participant
        $participant = new EventParticipant();
        $participant->setEvent($this);
        $participant->setUser($user);
        $participant->setRole($role ?? ParticipantRole::Participant);
        $this->participants->add($participant);
        return $participant;
    }

    public function removeParticipant(User $user): bool
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser() === $user) {
                $this->participants->removeElement($participant);
                return true;
            }
        }
        return false;
    }

    public function hasParticipant(User $user): bool
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser() === $user) {
                return true;
            }
        }
        return false;
    }

    public function isUserOrganizer(User $user): bool
    {
        foreach ($this->participants as $participant) {
            if ($participant->getUser() === $user && $participant->isOrganizer()) {
                return true;
            }
        }
        return false;
    }

    public function getParticipantCount(): int
    {
        return $this->participants->count();
    }

    public function getAttendeeCount(): int
    {
        return array_reduce($this->participants->toArray(), function($carry, $participant) {
            return $carry + ($participant->hasAttended() ? 1 : 0);
        }, 0);
    }
}
