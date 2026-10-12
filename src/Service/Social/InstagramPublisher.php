<?php
// src/Service/Social/InstagramPublisher.php

namespace App\Service\Social;

use App\Entity\BlogPost;
use App\Entity\SocialNetworkSetting;
use App\Enum\SocialPlatform;

/**
 * Instagram exige une IMAGE par post (API Graph). Sans image de
 * couverture sur l'article, la publication échoue proprement.
 */
class InstagramPublisher extends AbstractSocialPublisher
{
    public function supports(): SocialPlatform
    {
        return SocialPlatform::Instagram;
    }

    protected function getToken(): ?string
    {
        return $_ENV['FACEBOOK_PAGE_TOKEN'] ?? null; // même token : compte Insta pro lié à la Page FB
    }

    public function publish(BlogPost $post, SocialNetworkSetting $setting): ?string
    {
        $this->assertConfigured();
        $igUserId = $setting->getExternalId();
        if ($igUserId === null || $igUserId === '') {
            throw new \RuntimeException('ID de compte Instagram professionnel manquant (Paramètres → Réseaux sociaux).');
        }
        if (!$post->hasImage()) {
            throw new \RuntimeException(
                'L\'article n\'a pas d\'image de couverture : Instagram exige une image pour chaque publication.'
            );
        }

        $image = $post->getImage();
        $imageUrl = $this->publicBaseUrl . '/uploads/blog_posts/' . $image;

        // Étape 1 : créer le conteneur média
        $container = $this->socialClient->request('POST', sprintf(
            'https://graph.facebook.com/v21.0/%s/media',
            $igUserId
        ), [
            'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
            'json' => [
                'image_url' => $imageUrl,
                'caption' => mb_substr($this->buildMessage($post), 0, 2200),
            ],
        ])->toArray();

        $creationId = $container['id'] ?? throw new \RuntimeException('Conteneur média Instagram non créé.');

        // Étape 2 : publier le conteneur
        $publish = $this->socialClient->request('POST', sprintf(
            'https://graph.facebook.com/v21.0/%s/media_publish',
            $igUserId
        ), [
            'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
            'json' => ['creation_id' => $creationId],
        ])->toArray();

        return $publish['id'] ?? null;
    }
}