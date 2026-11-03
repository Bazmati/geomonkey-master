<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Fil d'Ariane automatique : la hiérarchie des pages est définie UNE fois
 * dans PARENTS (route enfant => route parent). Le template appelle juste
 * {{ breadcrumb() }} sans argument ; l'extension lit la route courante et
 * remonte la chaîne. Une nouvelle page = une ligne dans PARENTS.
 */
class BreadcrumbExtension extends AbstractExtension
{
    /**
     * Hiérarchie : 'route_enfant' => 'route_parent'.
     * La page d'accueil (racine) n'y figure pas.
     */
    private const PARENTS = [
        // Front — blog
        'app_blog_index'    => 'app_home',
        'app_blog_category' => 'app_blog_index',
        'app_blog_tag'      => 'app_blog_index',
        'app_blog_show'     => 'app_blog_index',
        // Front — événements
        'app_event_index'   => 'app_home',
        'app_event_show'    => 'app_event_index',
        // Front — autres pages
        'app_gallery_index'       => 'app_home',
        'app_presentation_index'  => 'app_home',
        'app_membership_register' => 'app_home',
        // Admin — blog (préfixe app_admin_blog_*)
        'app_admin_blog_index'        => 'app_admin_dashboard',
        'app_admin_blog_new'          => 'app_admin_blog_index',
        'app_admin_blog_edit'         => 'app_admin_blog_index',
        'admin_blog_category_index'   => 'app_admin_dashboard',
        'admin_blog_category_new'     => 'admin_blog_category_index',
        'admin_blog_category_edit'    => 'admin_blog_category_index',
        'admin_blog_tag_index'        => 'app_admin_dashboard',
        'admin_blog_tag_new'          => 'admin_blog_tag_index',
        'admin_blog_tag_edit'         => 'admin_blog_tag_index',
        // Admin — événements
        'admin_events_index' => 'app_admin_dashboard',
        'admin_events_new'   => 'admin_events_index',
        'admin_events_edit'  => 'admin_events_index',
        // Admin — galerie
        'app_admin_gallery_image_index' => 'app_admin_dashboard',
        'app_admin_gallery_image_new'   => 'app_admin_gallery_image_index',
        'app_admin_gallery_image_edit'  => 'app_admin_gallery_image_index',
        // Admin — adhésions
        'app_admin_membership_requests' => 'app_admin_dashboard',
        'app_admin_membership_list'      => 'app_admin_dashboard',
        'app_admin_membership_fee_index' => 'app_admin_dashboard',
        'app_admin_membership_fee_edit'  => 'app_admin_membership_fee_index',
        // Admin — présentation
        'app_admin_presentation_index' => 'app_admin_dashboard',
        'app_admin_presentation_new'   => 'app_admin_presentation_index',
        'app_admin_presentation_edit'  => 'app_admin_presentation_index',
        // Admin — utilisateurs
        'app_admin_user_index' => 'app_admin_dashboard',
        'app_admin_user_new'   => 'app_admin_user_index',
        'app_admin_user_edit'  => 'app_admin_user_index',
        // Admin — réseaux sociaux
        'app_admin_social_index' => 'app_admin_dashboard',
    ];

    private const LABELS = [
        'app_home'                => 'Accueil',
        'app_blog_index'          => 'Actualités',
        'app_event_index'         => 'Événements',
        'app_gallery_index'       => 'Galerie',
        'app_presentation_index'  => 'Présentation',
        'app_membership_register' => 'Rejoindre',
        'app_admin_dashboard'     => 'Backoffice',
        // Admin — blog
        'app_admin_blog_index'      => 'Articles',
        'app_admin_blog_new'       => 'Créer',
        'app_admin_blog_edit'      => 'Modifier',
        'admin_blog_category_index' => 'Catégories blog',
        'admin_blog_category_new'   => 'Créer',
        'admin_blog_category_edit'  => 'Modifier',
        'admin_blog_tag_index' => 'Tags blog',
        'admin_blog_tag_new'   => 'Créer',
        'admin_blog_tag_edit'  => 'Modifier',
        // Admin — événements
        'admin_events_index' => 'Événements',
        'admin_events_new'   => 'Créer',
        'admin_events_edit'  => 'Modifier',
        // Admin — galerie
        'app_admin_gallery_image_index' => 'Galerie',
        'app_admin_gallery_image_new'   => 'Créer',
        'app_admin_gallery_image_edit'  => 'Modifier',
        // Admin — adhésions
        'app_admin_membership_requests' => 'Demandes d\'adhésion',
        'app_admin_membership_list'      => 'Adhésions',
        'app_admin_membership_fee_index' => 'Cotisations',
        'app_admin_membership_fee_edit'  => 'Modifier',
        // Admin — présentation
        'app_admin_presentation_index' => 'Présentation',
        'app_admin_presentation_new'   => 'Créer',
        'app_admin_presentation_edit'  => 'Modifier',
        // Admin — utilisateurs
        'app_admin_user_index' => 'Utilisateurs',
        'app_admin_user_new'   => 'Créer',
        'app_admin_user_edit'  => 'Modifier',
        // Admin — réseaux sociaux
        'app_admin_social_index' => 'Réseaux sociaux',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

        /**
     * Racine du fil : Accueil pour le front, Backoffice pour l'admin.
     */
    private function rootRoute(string $route): string
    {
        return str_starts_with($route, 'app_admin_') || str_starts_with($route, 'admin_')
            ? 'app_admin_dashboard'
            : 'app_home';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('breadcrumb', [$this, 'buildBreadcrumb'], [
                'needs_environment' => true,
                'is_safe' => ['html'],
            ]),
        ];
    }

    /**
     * @param array{label?: string} $override  libellé de la page courante si différent
     */
    public function buildBreadcrumb(\Twig\Environment $twig, array $override = []): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return '';
        }

        $route = $request->attributes->get('_route');
        if ($route === null || $route === 'app_home') {
            return '';
        }

        $root = $this->rootRoute($route);

        // Remonte la chaîne enfant => parent => … => Accueil (une seule fois)
        $chain = [];
        $current = $route;
        $guard = 0;
        while ($current !== null && $guard++ < 10) {
            $chain[] = $current;
            if ($current === $root || $current === 'app_home') {
                break;
            }
            $parent = self::PARENTS[$current] ?? null;
            // Sans parent déclaré : on rattache à la racine du contexte,
            // jamais à app_home depuis l'admin.
            $current = $parent ?? $root;
        }

        $items = [];
        foreach (array_reverse($chain) as $i => $r) {
            $isLast = $i === count($chain) - 1;

            if ($isLast) {
                // Page courante : JAMAIS d'URL générée. Libellé par ordre de priorité :
                // override passé au template > entité dans les attributs > libellé statique.
                $label = $override['label']
                    ?? $this->entityLabel($request)
                    ?? self::LABELS[$r]
                    ?? $r;
                $items[] = ['label' => $label, 'url' => null];
                continue;
            }

            // Pages parentes : URL générée, en tolérant les routes à paramètres
            // (si un parent a besoin de paramètres, il devient non cliquable).
            try {
                $url = $this->urlGenerator->generate($r);
            } catch (\Throwable) {
                $url = null;
            }
            $items[] = ['label' => self::LABELS[$r] ?? $r, 'url' => $url];
        }

        return $twig->render('partials/_breadcrumb.html.twig', ['items' => $items]);
    }

    /**
     * Cherche une entité "labelisable" dans les attributs de la requête
     * (contrôleurs qui utilisent ParamConverter / MapEntity).
     */
    private function entityLabel(\Symfony\Component\HttpFoundation\Request $request): ?string
    {
        foreach (['event', 'post', 'category', 'tag', 'blogPost'] as $key) {
            $attr = $request->attributes->get($key);
            if (is_object($attr) && method_exists($attr, '__toString')) {
                return (string) $attr;
            }
        }

        return null;
    }
}