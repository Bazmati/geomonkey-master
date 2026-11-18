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
    private const JOURS_FR = [
        1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
        5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche',
    ];

    private const MOIS_FR = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];

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
            new TwigFilter('date_fr', [$this, 'formatDateFr']),
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

        return $this->imageManager->getCoverUrl($entity, $size);
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

    /**
     * Formate une date en français : « mercredi 18 novembre 2026 ».
     * N'utilise ni ext-intl ni twig/intl-extra.
     *
     * @param \DateTimeInterface|string $date Une date, une chaîne comprise par DateTime, ou 'now'
     */
    public function formatDateFr(\DateTimeInterface|string $date): string
    {
        $date = $date instanceof \DateTimeInterface ? $date : new \DateTimeImmutable($date);

        return sprintf(
            '%s %d %s %d',
            self::JOURS_FR[(int) $date->format('N')],
            (int) $date->format('j'),
            self::MOIS_FR[(int) $date->format('n')],
            $date->format('Y'),
        );
    }
}
