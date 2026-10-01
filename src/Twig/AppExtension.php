<?php

namespace App\Twig;

use App\Service\ImageManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Extension Twig pour GeoMonkey.
 * 
 * Fournit des filtres personnalisés pour les images.
 */
class AppExtension extends AbstractExtension
{
    public function __construct(
        private ImageManager $imageManager
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('image_url', [$this, 'getImageUrl']),
        ];
    }

    /**
     * Retourne l'URL d'une image dans une taille spécifique.
     * 
     * @param mixed $entity L'entité qui implémente ImageableInterface
     * @param string $size La taille (small, medium, large)
     * @return string|null L'URL ou null si pas d'image
     */
    public function getImageUrl(mixed $entity, string $size = 'medium'): ?string
    {
        if (!method_exists($entity, 'getImage') || !method_exists($entity, 'getImagePath')) {
            return null;
        }

        return $this->imageManager->getUrl($entity, $size);
    }
}
