<?php

namespace App\Service;

use App\Entity\BlogPost;
use App\Interface\SocialPublisherInterface;
use App\Repository\SocialNetworkSettingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Orchestrateur : un article mis en ligne → publication sur chaque
 * réseau "autoPublishReady". Les échecs d'un réseau ne bloquent
 * pas les autres : tout est loggé.
 */
class SocialPublishService
{
    /** @var array<string, SocialPublisherInterface> indexé par valeur de plateforme */
    private array $publishers = [];

    public function __construct(
        private SocialNetworkSettingRepository $settingRepo,
        private LoggerInterface $logger,
        #[TaggedIterator('app.social_publisher')]
        iterable $publishers,
    ) {
        foreach ($publishers as $publisher) {
            $this->publishers[$publisher->supports()->value] = $publisher;
        }
    }

    /**
     * Publie l'article sur tous les réseaux configurés.
     * Ne lève JAMAIS : un réseau en échec est loggé, la suite continue.
     */
    public function publishBlogPost(BlogPost $post, string $publicBaseUrl): void
    {
        $settings = $this->settingRepo->findAutoPublishReady();

        if ($settings === []) {
            $this->logger->info('Aucun réseau social configuré pour la publication automatique.');
            return;
        }

        foreach ($settings as $setting) {
            $platform = $setting->getPlatform();
            $key = $platform->value;

            if (!isset($this->publishers[$key])) {
                $this->logger->warning('Aucun publisher enregistré pour {platform}.', ['platform' => $key]);
                continue;
            }

            $publisher = $this->publishers[$key];
            if ($publisher instanceof \App\Service\Social\AbstractSocialPublisher) {
                $publisher->setPublicBaseUrl($publicBaseUrl);
            }

            try {
                $remoteId = $publisher->publish($post, $setting);
                $this->logger->info('Article publié sur {platform} (id distant : {id}).', [
                    'platform' => $key,
                    'id' => $remoteId ?? 'n/a',
                    'blog_post' => $post->getId(),
                ]);
            } catch (\Throwable $e) {
                // Un échec réseau ne casse pas la publication des autres réseaux
                $this->logger->error('Échec de publication sur {platform} : {error}', [
                    'platform' => $key,
                    'error' => $e->getMessage(),
                    'blog_post' => $post->getId(),
                ]);
            }
        }
    }
}