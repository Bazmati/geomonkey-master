<?php

namespace App\Service;

use App\Interface\ImageableInterface;
use Intervention\Image\ImageManagerStatic as InterventionImage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Service for managing image uploads, resizing, and deletion.
 * Works with any entity that implements ImageableInterface.
 */
class ImageManager
{
    private string $publicDir;
    private array $sizes;
    private array $qualities;

    /**
     * Available image sizes and their max width.
     */
    private const AVAILABLE_SIZES = ['small', 'medium', 'large'];

    public function __construct(string $publicDir)
    {
        $this->publicDir = rtrim($publicDir, '/');
        
        // Configuration des tailles (max width) et qualités
        $this->sizes = [
            'small' => 300,
            'medium' => 800,
            'large' => 1200,
        ];
        
        $this->qualities = [
            'small' => 80,
            'medium' => 85,
            'large' => 90,
        ];
    }

    /**
     * Upload and process an image for an entity.
     * Generates small, medium, and large versions in WebP format.
     */
    public function upload(ImageableInterface $entity, UploadedFile $file): void
    {
        // Delete old images if they exist
        $this->deleteForEntity($entity);

        // Generate UUID for the image
        $uuid = bin2hex(random_bytes(16));

        // Process each size
        foreach (self::AVAILABLE_SIZES as $size) {
            $this->processImage($entity, $file, $uuid, $size);
        }

        // Store the UUID in the entity
        $entity->setImage($uuid);
    }

    /**
     * Process a single image size and save it.
     */
    private function processImage(
        ImageableInterface $entity,
        UploadedFile $file,
        string $uuid,
        string $size
    ): void {
        $maxWidth = $this->sizes[$size];
        $quality = $this->qualities[$size];

        // Create intervention image
        $image = InterventionImage::make($file->getPathname());

        // Resize while maintaining aspect ratio
        $image->resize($maxWidth, null, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize(); // Don't upsize if image is smaller
        });

        // Get the save path
        $savePath = $this->getAbsolutePath($entity, $size, $uuid);
        
        // Ensure directory exists
        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save as WebP
        $image->save($savePath, $quality, 'webp');
    }

    /**
     * Delete all images for an entity.
     */
    public function deleteForEntity(ImageableInterface $entity): void
    {
        if (!$entity->getImage()) {
            return;
        }

        $uuid = $entity->getImage();
        $basePath = $this->publicDir . '/uploads/' . $entity->getImagePath() . '/' . $entity->getId();

        foreach (self::AVAILABLE_SIZES as $size) {
            $filePath = $basePath . '/' . $size . '/' . $uuid . '.webp';
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // Remove empty directories
        $this->cleanupEmptyDirectories($basePath);
    }

    /**
     * Recursively remove empty directories.
     */
    private function cleanupEmptyDirectories(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $fullPath = $path . '/' . $file;
            if (is_dir($fullPath)) {
                $this->cleanupEmptyDirectories($fullPath);
            }
        }

        // Remove directory if empty
        if (count(array_diff(scandir($path), ['.', '..'])) === 0) {
            rmdir($path);
        }
    }

    /**
     * Get the URL for an entity's image in a specific size.
     * Returns the URL or null if no image exists.
     */
    public function getUrl(ImageableInterface $entity, string $size = 'medium'): ?string
    {
        if (!$entity->getImage()) {
            return null;
        }

        return '/' . $this->getRelativePath($entity, $size, $entity->getImage());
    }

    /**
     * Get the HTML tag for an entity's image.
     * If no image exists, returns Bootstrap's image-slash icon.
     * If a default image is defined, uses that instead.
     */
    public function getHtmlTag(
        ImageableInterface $entity,
        string $size = 'medium',
        string $alt = '',
        string $class = 'img-cover',
        array $attrs = []
    ): string {
        $url = $this->getUrl($entity, $size);

        if ($url) {
            $attrsStr = '';
            $attrs['class'] = $class;
            $attrs['src'] = $url;
            $attrs['alt'] = $alt ?: ($entity->getImagePath() ?? 'image');

            foreach ($attrs as $key => $value) {
                $attrsStr .= sprintf(' %s="%s"', $key, htmlspecialchars($value, ENT_QUOTES));
            }

            return sprintf('<img%s>', $attrsStr);
        }

        // No image: check for default image
        $defaultImage = $entity->getDefaultImage();
        if (!empty($defaultImage)) {
            return sprintf(
                '<img src="%s" alt="%s" class="%s">',
                htmlspecialchars($defaultImage, ENT_QUOTES),
                htmlspecialchars($alt ?: 'default', ENT_QUOTES),
                htmlspecialchars($class, ENT_QUOTES)
            );
        }

        // Fallback to Bootstrap icon
        return '<i class="bi bi-image-slash text-muted"></i>';
    }

    /**
     * Get the absolute filesystem path for an image.
     */
    private function getAbsolutePath(
        ImageableInterface $entity,
        string $size,
        string $uuid
    ): string {
        return sprintf(
            '%s/uploads/%s/%s/%s/%s.webp',
            $this->publicDir,
            $entity->getImagePath(),
            $entity->getId(),
            $size,
            $uuid
        );
    }

    /**
     * Get the relative URL path for an image.
     */
    private function getRelativePath(
        ImageableInterface $entity,
        string $size,
        string $uuid
    ): string {
        return sprintf(
            'uploads/%s/%s/%s/%s.webp',
            $entity->getImagePath(),
            $entity->getId(),
            $size,
            $uuid
        );
    }

    /**
     * Get available sizes.
     */
    public function getAvailableSizes(): array
    {
        return self::AVAILABLE_SIZES;
    }

    /**
     * Check if a size is valid.
     */
    public function isValidSize(string $size): bool
    {
        return in_array($size, self::AVAILABLE_SIZES, true);
    }
}
