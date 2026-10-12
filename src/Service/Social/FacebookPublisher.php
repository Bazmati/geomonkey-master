<?php
// src/Service/Social/FacebookPublisher.php

namespace App\Service\Social;

use App\Entity\BlogPost;
use App\Entity\SocialNetworkSetting;
use App\Enum\SocialPlatform;

class FacebookPublisher extends AbstractSocialPublisher
{
    public function supports(): SocialPlatform
    {
        return SocialPlatform::Facebook;
    }

    protected function getToken(): ?string
    {
        return $_ENV['FACEBOOK_PAGE_TOKEN'] ?? null;
    }

    public function publish(BlogPost $post, SocialNetworkSetting $setting): ?string
    {
        $this->assertConfigured();
        $pageId = $setting->getExternalId();
        if ($pageId === null || $pageId === '') {
            throw new \RuntimeException('ID de page Facebook manquant (Paramètres → Réseaux sociaux).');
        }

        $response = $this->socialClient->request('POST', sprintf(
            'https://graph.facebook.com/v21.0/%s/feed',
            $pageId
        ), [
            'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
            'json' => [
                'message' => $this->buildMessage($post),
                'link' => $this->getArticleUrl($post),
            ],
        ]);

        $data = $response->toArray();

        return $data['id'] ?? $data['post_id'] ?? null;
    }
}