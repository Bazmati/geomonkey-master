<?php

namespace App\Entity;

use App\Enum\ProjectTaskState;
use App\Repository\ProjectTaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tâche d'un projet (carte du tableau de suivi type Trello).
 *
 * Une tâche a un titre, une description détaillée, un état d'avancement
 * (à faire / en cours / fait), un responsable désigné, et peut être
 * reliée à des documents (fichiers uploadés et/ou images de la galerie).
 */
#[ORM\Entity(repositoryClass: ProjectTaskRepository::class)]
class ProjectTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre de la tâche est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le titre doit faire au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(enumType: ProjectTaskState::class)]
    private ProjectTaskState $state = ProjectTaskState::Todo;

    /**
     * Ordre d'affichage dans sa colonne (tri croissant).
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /**
     * Responsable de la tâche (peut en changer l'état).
     * Le chef de projet peut aussi toujours changer l'état d'une tâche.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    /**
     * Images de la galerie rattachées à cette tâche (documentation).
     */
    #[ORM\ManyToMany(targetEntity: GalleryImage::class)]
    private Collection $galleryImages;

    /**
     * Fichiers uploadés rattachés à cette tâche (PDF, images, etc.).
     */
    #[ORM\OneToMany(targetEntity: ProjectTaskDocument::class, mappedBy: 'task', cascade: ['persist'], orphanRemoval: true)]
    private Collection $documents;

    public function __construct()
    {
        $this->galleryImages = new ArrayCollection();
        $this->documents = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
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

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getState(): ProjectTaskState
    {
        return $this->state;
    }

    public function setState(ProjectTaskState $state): static
    {
        $this->state = $state;

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

    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): static
    {
        $this->assignee = $assignee;

        return $this;
    }

    /** @return Collection<int, GalleryImage> */
    public function getGalleryImages(): Collection
    {
        return $this->galleryImages;
    }

    public function addGalleryImage(GalleryImage $galleryImage): static
    {
        if (!$this->galleryImages->contains($galleryImage)) {
            $this->galleryImages->add($galleryImage);
        }

        return $this;
    }

    public function removeGalleryImage(GalleryImage $galleryImage): static
    {
        $this->galleryImages->removeElement($galleryImage);

        return $this;
    }

    /** @return Collection<int, ProjectTaskDocument> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(ProjectTaskDocument $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setTask($this);
        }

        return $this;
    }

    public function removeDocument(ProjectTaskDocument $document): static
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getTask() === $this) {
                $document->setTask(null);
            }
        }

        return $this;
    }
}
