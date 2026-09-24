<?php

namespace App\Twig;

use App\Interface\ImageableInterface;
use App\Service\ImageManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for image-related functions.
 * Provides helpers for displaying images with fallback to Bootstrap icon.
 */
class ImageExtension extends AbstractExtension
{
    public function __construct(private ImageManager $imageManager) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image_url', [$this, 'getImageUrl'], ['is_safe' => ['html']]),
            new TwigFunction('image_tag', [$this, 'getImageTag'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Get the URL for an entity's image in a specific size.
     * Returns null if no image exists.
     */
    public function getImageUrl(ImageableInterface $entity, string $size = 'medium'): ?string
    {
        return $this->imageManager->getUrl($entity, $size);
    }

    /**
     * Get the HTML <img> tag for an entity's image.
     * If no image exists and no default is set, returns Bootstrap's image-slash icon.
     * 
     * @param ImageableInterface $entity The entity with an image
     * @param string $size The size (small, medium, large)
     * @param string $alt Alternative text for the image
     * @param string $class CSS class(es) for the image
     * @param array $attrs Additional HTML attributes
     */
    public function getImageTag(
        ImageableInterface $entity,
        string $size = 'medium',
        string $alt = '',
        string $class = 'img-cover',
        array $attrs = []
    ): string {
        return $this->imageManager->getHtmlTag($entity, $size, $alt, $class, $attrs);
    }
}
