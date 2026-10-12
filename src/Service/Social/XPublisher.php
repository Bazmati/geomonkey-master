<?php
// src/Service/Social/XPublisher.php

namespace App\Service\Social;

use App\Entity\BlogPost;
use App\Entity\SocialNetworkSetting;
use App\Enum\SocialPlatform;

class XPublisher extends AbstractSocialPublisher
{
    public function supports(): SocialPlatform
    {
        return SocialPlatform::X;
    }

    protected function getToken(): ?string
    {
        return $_ENV['X_BEARER_TOKEN'] ?? null;
    }

    public function publish(BlogPost $post, SocialNetworkSetting $setting): ?string
    {
        $this->assertConfigured();

        $response = $this->socialClient->request('POST', 'https://api.twitter.com/2/tweets', [
            'headers' => ['Authorization' => 'Bearer ' . $this->getToken()],
            'json' => [
                // X : 280 caractères max — titre + lien
                'text' => mb_substr($post->getTitle(), 0, 200) . "\n" . $this->getArticleUrl($post),
            ],
        ]);

        $data = $response->toArray();

        return $data['data']['id'] ?? null;
    }
}