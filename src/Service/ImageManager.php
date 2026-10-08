<?php

namespace App\Service;

use App\Interface\ImageableInterface;
use Intervention\Image\ImageManager as InterventionImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Format;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * @phpstan-type InterventionImage \Intervention\Image\Image
 */

/**
 * Service for managing image uploads, resizing, and deletion.
 * Works with any entity that implements ImageableInterface.
 */
class ImageManager
{
    private string $publicDir;
    private array $sizes;
    private array $qualities;
    private array $urlCache = [];

    /**
     * Available image sizes and their max width.
     */
    private const AVAILABLE_SIZES = ['small', 'medium', 'large'];

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg', 'image/pjpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(string $publicDir)
    {
        $this->publicDir = rtrim($publicDir, '/');
        
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
        $this->validateUploadedFile($file);

        $uuid = bin2hex(random_bytes(16));
        $writtenPaths = [];

        try {
            $manager = $this->createImageManager();

            foreach (self::AVAILABLE_SIZES as $size) {
                $savePath = $this->getAbsolutePath($entity, $size, $uuid);
                $this->processImage($entity, $file, $uuid, $size, $manager);
                $writtenPaths[] = $savePath;
            }
        } catch (\Throwable $e) {
            // Échec partiel : on supprime ce qui a déjà été écrit
            foreach ($writtenPaths as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            throw $e; // on relance : l'appelant doit savoir que l'upload a échoué
        }

        $entity->setImage($uuid);
    }

    /**
     * Validate uploaded file for security.
     */
    private function validateUploadedFile(UploadedFile $file): void
    {
        // 1. Check if file is valid
        if (!$file->isValid()) {
            throw new FileException('Fichier invalide: ' . $file->getErrorMessage());
        }

        // 2. Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new FileException('Fichier trop volumineux (max 5MB)');
        }

        // 3. Check MIME type from client
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new FileException('Type de fichier non autorisé: ' . $mimeType);
        }

        // 4. Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new FileException('Extension de fichier non autorisée: ' . $extension);
        }

        // 5. Verify real MIME type using finfo (OO style)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getPathname());

        if (!in_array($realMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new FileException('Le fichier n\'est pas une image valide');
        }

        // 6. Check image dimensions (only if possible)
        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo !== false) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];

            if ($width > 5000 || $height > 5000) {
                throw new FileException('Image trop grande (max 5000x5000px)');
            }
        }
        // If getimagesize fails but finfo says it's a valid image, we accept it
        // This can happen with some base64 encoded test images
    }

    /**
     * Process a single image size and save it.
     */
    private function processImage(
        ImageableInterface $entity,
        UploadedFile $file,
        string $uuid,
        string $size,
        ?InterventionImageManager $manager
    ): void {
        if ($manager === null) {
            $this->copyFileWithoutProcessing($entity, $file, $uuid, $size);
            return;
        }

        $maxWidth = $this->sizes[$size];
        $quality = $this->qualities[$size];

        $savePath = $this->getAbsolutePath($entity, $size, $uuid);
        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // API v4 : lecture via decodePath
        $image = $manager->decodePath($file->getPathname());

        // Resize à la largeur max, sans jamais agrandir
        $image->resizeDown(width: $maxWidth);

        // 🔒 RGPD : le ré-encodage WebP reconstruit l'image depuis les pixels,
        // les métadonnées (EXIF, GPS, ICC) ne sont pas copiées.
        $encoded = $image->encodeUsingFormat(Format::WEBP, quality: $quality);

        $encoded->save($savePath);
    }

    /**
     * Create an ImageManager instance based on available extensions.
     */
    private function createImageManager(): ?InterventionImageManager
    {
        if (extension_loaded('gd')) {
            return InterventionImageManager::usingDriver(GdDriver::class);
        }
        if (extension_loaded('imagick')) {
            return InterventionImageManager::usingDriver(ImagickDriver::class);
        }
        return null;
    }

    /**
     * Copy file without image processing (fallback when GD/Imagick not available).
     */
    private function copyFileWithoutProcessing(
        ImageableInterface $entity,
        UploadedFile $file,
        string $uuid,
        string $size
    ): void {
        // Validate file even in fallback mode
        $this->validateUploadedFile($file);
        
        // Get original extension
        $originalExtension = strtolower($file->getClientOriginalExtension());
        if (empty($originalExtension)) {
            $originalExtension = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
        }
        
        // Ensure extension is allowed
        if (!in_array($originalExtension, self::ALLOWED_EXTENSIONS, true)) {
            $originalExtension = 'webp';
        }
        
        $savePath = $this->getAbsolutePath($entity, $size, $uuid);
        // Replace .webp with original extension
        $savePath = preg_replace('/\.webp$/', '.' . $originalExtension, $savePath);
        
        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($file->getPathname(), $savePath);
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
        $entityId = $entity->getId();

        if ($entityId === null) {
            return;
        }

        $imagePath = $entity->getImagePath();
        $extensions = ['webp', 'jpg', 'jpeg', 'png', 'gif'];
        $sizes = ['small', 'medium', 'large'];

        // Racines possibles : standard (id), legacy (sans id), temp (ancien bug d'upload)
        $roots = [
            $this->publicDir . '/uploads/' . $imagePath . '/' . $entityId,
            $this->publicDir . '/uploads/' . $imagePath,       // ancien format sans id
            $this->publicDir . '/uploads/' . $imagePath . '/temp',
        ];

        foreach ($roots as $root) {
            foreach ($sizes as $size) {
                foreach ($extensions as $ext) {
                    $filePath = $root . '/' . $size . '/' . $uuid . '.' . $ext;
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }

        // Nettoyage des dossiers vides devenus inutiles
        $this->cleanupEmptyDirectories($this->publicDir . '/uploads/' . $imagePath . '/' . $entityId);
        $this->cleanupEmptyDirectories($this->publicDir . '/uploads/' . $imagePath . '/temp');
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
        if (count(array_diff(scandir($path), ['.', '..'])) === 0) {
            rmdir($path);
        }
    }

    /**
     * Get the URL for an entity's image in a specific size.
     */
    public function getUrl(ImageableInterface $entity, string $size = 'medium'): ?string
    {
        if (!$entity->getImage()) {
            return null;
        }
        
        $uuid = $entity->getImage();
        $entityId = $entity->getId();
        $imagePath = $entity->getImagePath();
        
        // Handle null entityId (before first flush)
        if ($entityId === null) {
            return null;
        }
        
        $cacheKey = $entityId . ':' . $imagePath . ':' . $uuid . ':' . $size;
        if (isset($this->urlCache[$cacheKey])) {
            return $this->urlCache[$cacheKey];
        }
        
        // Essayez le chemin standard (avec entityId)
        $basePath = 'uploads/' . $imagePath . '/' . $entityId . '/' . $size . '/' . $uuid;
        
        // Try .webp first (if GD/Imagick is installed)
        $webpPath = $this->publicDir . '/' . $basePath . '.webp';
        if (file_exists($webpPath)) {
            return $this->urlCache[$cacheKey] = '/' . $basePath . '.webp';
        }
        
        // Fallback to common extensions if GD/Imagick not installed
        $extensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp'];
        foreach ($extensions as $ext) {
            $fullPath = $this->publicDir . '/' . $basePath . $ext;
            if (file_exists($fullPath)) {
                return $this->urlCache[$cacheKey] = '/' . $basePath . $ext;
            }
        }
        
        // Essayer le chemin SANS entityId (compatibilité avec anciens uploads)
        // C'est le cas où les images ont été uploadées avant que l'entité n'ait un ID
        $legacyBasePath = 'uploads/' . $imagePath . '/' . $size . '/' . $uuid;
        foreach ($extensions as $ext) {
            $legacyPath = $this->publicDir . '/' . $legacyBasePath . $ext;
            if (file_exists($legacyPath)) {
                return $this->urlCache[$cacheKey] = '/' . $legacyBasePath . $ext;
            }
        }
        
        // Si rien ne fonctionne, retourner null
        return null;
    }


    /**
     * Get the absolute filesystem path for an image.
     */
    private function getAbsolutePath(
        ImageableInterface $entity,
        string $size,
        string $uuid
    ): string {
        $entityId = $entity->getId();
        
        // Si l'entité n'a pas encore d'ID (avant flush), utiliser un chemin temporaire
        // qui sera corrigé après le flush. Mais pour l'instant, on utilise l'UUID seul.
        // Cependant, une fois persistée, l'entité aura un ID et on pourra accéder aux images.
        // Pour les images existantes créées avant ce fix, on essaie aussi sans ID.
        if ($entityId === null) {
            // Avant le flush, on ne peut pas encore créer le chemin final
            // Retourner un chemin temporaire qui sera mis à jour après
            return sprintf(
                '%s/uploads/%s/temp/%s/%s.webp',
                $this->publicDir,
                $entity->getImagePath(),
                $size,
                $uuid
            );
        }
        
        return sprintf(
            '%s/uploads/%s/%s/%s/%s.webp',
            $this->publicDir,
            $entity->getImagePath(),
            $entityId,
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

    public function getAvailableSizes(): array
    {
        return self::AVAILABLE_SIZES;
    }

    public function isValidSize(string $size): bool
    {
        return in_array($size, self::AVAILABLE_SIZES, true);
    }
}
