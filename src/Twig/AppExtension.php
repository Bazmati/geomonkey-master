<?php

namespace App\Twig;

use App\Service\ImageManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

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

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_url', [$this, 'getImageUrl']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('truncate', [$this, 'truncateText']),
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

    /**
     * Tronque un texte à une longueur maximale.
     * 
     * @param string $text Le texte à tronquer
     * @param int $length La longueur maximale (par défaut 50)
     * @param string $suffix Le suffixe à ajouter (par défaut '...')
     * @return string Le texte tronqué
     */
    public function truncateText(string $text, int $length = 50, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . $suffix;
    }
}
