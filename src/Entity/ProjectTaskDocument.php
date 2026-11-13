<?php

namespace App\Entity;

use App\Repository\ProjectTaskDocumentRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fichier uploadé rattaché à une tâche de projet (PDF, image, document…).
 *
 * Stocké hors de public/, dans var/task_documents/{taskId}/ (chemin relatif
 * retourné par getStorageRelativePath()) ; le nom en base est le nom
 * technique du fichier sur le disque, le nom d'origine est conservé pour
 * l'affichage et le téléchargement.
 */
#[ORM\Entity(repositoryClass: ProjectTaskDocumentRepository::class)]
class ProjectTaskDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProjectTask::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ProjectTask $task = null;

    /**
     * Nom du fichier sur le disque (unique, généré à l'upload).
     */
    #[ORM\Column(length: 255)]
    private ?string $fileName = null;

    /**
     * Nom d'origine du fichier uploadé (affiché à l'utilisateur).
     */
    #[ORM\Column(length: 255)]
    private ?string $originalName = null;

    #[ORM\Column(length: 100)]
    private ?string $mimeType = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTask(): ?ProjectTask
    {
        return $this->task;
    }

    public function setTask(?ProjectTask $task): static
    {
        $this->task = $task;

        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getUploadedBy(): ?User
    {
        return $this->uploadedBy;
    }

    public function setUploadedBy(?User $uploadedBy): static
    {
        $this->uploadedBy = $uploadedBy;

        return $this;
    }

    /**
     * Chemin relatif du fichier, par rapport à var/
     * (ou à public/uploads/ pour les fichiers historiques non migrés).
     */
    public function getStorageRelativePath(): string
    {
        return 'task_documents/' . $this->task?->getId() . '/' . $this->fileName;
    }

    /**
     * Icône Bootstrap selon le type de fichier.
     */
    public function getIcon(): string
    {
        return match (true) {
            str_starts_with((string) $this->mimeType, 'image/') => 'bi-file-image',
            $this->mimeType === 'application/pdf' => 'bi-file-pdf',
            str_starts_with((string) $this->mimeType, 'video/') => 'bi-file-play',
            str_starts_with((string) $this->mimeType, 'audio/') => 'bi-file-music',
            default => 'bi-file-earmark',
        };
    }

    /**
     * Taille lisible pour l'affichage.
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->size ?? 0;
        $units = ['o', 'Ko', 'Mo', 'Go'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
