<?php

namespace App\Interface;

use App\Entity\BlogPost;
use App\Entity\SocialNetworkSetting;
use App\Enum\SocialPlatform;

/**
 * Un publisher sait publier un article sur UN réseau social.
 * Chaque implémentation vit dans src/Service/Social/ et est
 * automatiquement collectée par SocialPublishService (tagged services).
 */
interface SocialPublisherInterface
{
    /**
     * La plateforme gée par ce publisher (ex : SocialPlatform::Facebook).
     */
    public function supports(): SocialPlatform;

    /**
     * Publie l'article et retourne l'identifiant du post distant
     * (null si le réseau ne le renvoie pas).
     *
     * @throws \RuntimeException en cas d'échec (API, token, quota…)
     */
    public function publish(BlogPost $post, SocialNetworkSetting $setting): ?string;
}