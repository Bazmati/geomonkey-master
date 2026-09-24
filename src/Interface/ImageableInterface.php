<?php

namespace App\Interface;

use Symfony\Component\HttpFoundation\File\File;

/**
 * Interface to be implemented by any entity that supports image uploads.
 */
interface ImageableInterface
{
    /**
     * Get the stored image identifier (UUID or path).
     */
    public function getImage(): ?string;

    /**
     * Set the stored image identifier.
     * Returns static for method chaining.
     */
    public function setImage(?string $image): static;

    /**
     * Get the uploaded file (temporary, not persisted).
     */
    public function getImageFile(): ?File;

    /**
     * Set the uploaded file.
     */
    public function setImageFile(?File $file): void;

    /**
     * Get the base path for this entity's images.
     * Example: "events", "posts", etc.
     */
    public function getImagePath(): string;

    /**
     * Get the default image to use when no image is set.
     * Should return the path to a default image (e.g., "default-event.webp").
     * If empty, the system will use Bootstrap's image-slash icon.
     */
    public function getDefaultImage(): string;

    /**
     * Check if the entity has an image.
     */
    public function hasImage(): bool;
}
