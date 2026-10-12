<?php

namespace App\Service\Social;

use App\Entity\BlogPost;
use App\Entity\SocialNetworkSetting;
use App\Interface\SocialPublisherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Socle commun : construction du message, appel HTTP, gestion d'erreur.
 * Les tokens sont lus dans .env.local — jamais stockés en base.
 */
abstract class AbstractSocialPublisher implements SocialPublisherInterface
{
    public function __construct(
        protected HttpClientInterface $socialClient, // client scopé, voir config
    ) {}

    /**
     * Token d'API pour ce réseau (variable d'environnement).
     * Ex : FACEBOOK_PAGE_TOKEN, LINKEDIN_ACCESS_TOKEN, X_BEARER_TOKEN.
     */
    abstract protected function getToken(): ?string;

    /**
     * URL complète du message, prête pour l'affichage public.
     */
    protected function getArticleUrl(BlogPost $post): string
    {
        // Renseigné par le service appelant via setPublicBaseUrl() ;
        // fallback : utilisation directe non recommandée hors CLI.
        return $this->publicBaseUrl . '/blog/' . $post->getSlug();
    }

    protected string $publicBaseUrl = '';

    public function setPublicBaseUrl(string $url): void
    {
        $this->publicBaseUrl = rtrim($url, '/');
    }

    /**
     * Corps du message réseau (chaque API a ses limites de longueur).
     */
    protected function buildMessage(BlogPost $post): string
    {
        return $post->getTitle() . "\n\n" . $post->getExcerpt(180) . "\n" . $this->getArticleUrl($post);
    }

    protected function assertConfigured(): void
    {
        $token = $this->getToken();
        if ($token === null || $token === '') {
            throw new \RuntimeException(sprintf(
                'Token d\'API manquant pour %s : définissez la variable d\'environnement attendue dans .env.local.',
                $this->supports()->label()
            ));
        }
    }
}