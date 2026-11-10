<?php

namespace App\Controller\Front;

use App\Repository\BlogPostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sitemap XML pour les moteurs de recherche
 *
 * Inclut les pages statiques + les articles de blog publiés.
 * Les routes inconnues sont ignorées silencieusement : le sitemap
 * reste valide même si une page n'existe pas encore.
 */
class SeoController extends AbstractController
{
    /**
     * Pages statiques à indexer, avec priorité et fréquence de mise à jour.
     * Adapter la liste si des routes sont ajoutées/renommées (debug:router).
     */
    private const STATIC_ROUTES = [
        // [route, paramètres, priorité, changefreq]
        ['app_home', [], 1.0, 'weekly'],
        ['app_blog_index', [], 0.9, 'daily'],
        ['app_donation', [], 0.7, 'monthly'],
        ['app_membership_register', [], 0.7, 'monthly'],
        ['app_presentation', [], 0.6, 'monthly'],
        ['app_gallery', [], 0.6, 'weekly'],
        ['app_events', [], 0.6, 'weekly'],
        ['app_cgu', [], 0.2, 'yearly'],
        ['app_privacy', [], 0.2, 'yearly'],
    ];

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(BlogPostRepository $blogPostRepository): Response
    {
        $urls = [];

        // Pages statiques
        foreach (self::STATIC_ROUTES as [$route, $params, $priority, $changefreq]) {
            try {
                $urls[] = [
                    'loc' => $this->generateUrl($route, $params, UrlGeneratorInterface::ABSOLUTE_URL),
                    'priority' => $priority,
                    'changefreq' => $changefreq,
                    'lastmod' => null,
                ];
            } catch (\Throwable) {
                // Route inexistante : on ignore
            }
        }

        // Articles de blog publiés
        foreach ($blogPostRepository->findPublishedPosts() as $post) {
            try {
                $urls[] = [
                    'loc' => $this->generateUrl('app_blog_show', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                    'priority' => 0.8,
                    'changefreq' => 'monthly',
                    'lastmod' => $post->getUpdatedAt() ?? $post->getPublishedAt() ?? $post->getCreatedAt(),
                ];
            } catch (\Throwable) {
                // on ignore
            }
        }

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset/>');
        $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $u = $xml->addChild('url');
            $u->addChild('loc', htmlspecialchars($url['loc'], ENT_XML1));
            if ($url['lastmod'] instanceof \DateTimeInterface) {
                $u->addChild('lastmod', $url['lastmod']->format('Y-m-d'));
            }
            if (isset($url['changefreq'])) {
                $u->addChild('changefreq', $url['changefreq']);
            }
            if (isset($url['priority'])) {
                $u->addChild('priority', (string) $url['priority']);
            }
        }

        $response = new Response($xml->asXML(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        // Les crawlers peuvent mettre en cache 1h sans souci
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
