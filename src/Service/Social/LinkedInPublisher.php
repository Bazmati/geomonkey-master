<?php
// src/Service/Social/LinkedInPublisher.php

namespace App\Service\Social;

use App\Entity\BlogPost;
use App\Entity\SocialNetworkSetting;
use App\Enum\SocialPlatform;

class LinkedInPublisher extends AbstractSocialPublisher
{
    public function supports(): SocialPlatform
    {
        return SocialPlatform::LinkedIn;
    }

    protected function getToken(): ?string
    {
        return $_ENV['LINKEDIN_ACCESS_TOKEN'] ?? null;
    }

    public function publish(BlogPost $post, SocialNetworkSetting $setting): ?string
    {
        $this->assertConfigured();
        $authorUrn = $setting->getExternalId();
        if ($authorUrn === null || $authorUrn === '') {
            throw new \RuntimeException('URN d\'organisation LinkedIn manquant (Paramètres → Réseaux sociaux).');
        }

        $response = $this->socialClient->request('POST', 'https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getToken(),
                'X-Restli-Protocol-Version' => '2.0.0',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'author' => $authorUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $this->buildMessage($post),
                        ],
                        'shareMediaCategory' => 'ARTICLE',
                        'originalUrl' => $this->getArticleUrl($post),
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ],
        ]);

        $data = $response->toArray();

        return $data['id'] ?? null;
    }
}