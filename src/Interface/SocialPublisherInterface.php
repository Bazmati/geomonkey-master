<?php

namespace App\Interface;

use App\Entity\BlogPost;
use App\Entity\SocialNetworkSetting;
use App\Enum\SocialPlatform;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un publisher sait publier un article sur UN réseau social.
 * L'attribut AutoconfigureTag tague automatiquement toutes les
 * implémentations avec le nom de l'interface : SocialPublishService
 * les collecte via #[AutowireIterator(self::class)] sans aucune config.
 */
#[AutoconfigureTag]
interface SocialPublisherInterface
{
    public function supports(): SocialPlatform;

    /**
     * @throws \RuntimeException en cas d'échec (API, token, quota…)
     */
    public function publish(BlogPost $post, SocialNetworkSetting $setting): ?string;
}