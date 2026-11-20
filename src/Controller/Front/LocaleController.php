<?php

namespace App\Controller\Front;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sélecteur de langue de la navbar : /locale/{fr|en}
 * - enregistre la locale en session ET en cookie (1 an) ;
 * - redirige vers la page d'origine (referer du même hôte, sinon accueil) ;
 * - la locale sera appliquée à la requête suivante par LocaleSubscriber.
 */
class LocaleController extends AbstractController
{
    #[Route(path: '/locale/{locale}', name: 'app_locale', requirements: ['locale' => 'fr|en'])]
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        // Referer du même hôte uniquement : pas d'open redirect.
        $referer = $request->headers->get('referer');
        $target = (\is_string($referer) && str_starts_with($referer, $request->getSchemeAndHttpHost()))
            ? $referer
            : $this->generateUrl('app_home');

        $response = new RedirectResponse($target);

        $response->headers->setCookie(new Cookie(
            name: LocaleSubscriber::COOKIE_NAME,
            value: $locale,
            expire: new \DateTimeImmutable('+1 year'),
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $response;
    }
}
