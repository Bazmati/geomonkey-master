<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * i18n sans URL : la langue choisie par le visiteur (bouton FR/EN
 * de la navbar) est relue à chaque requête, priorité session > cookie.
 * Les URLs restent identiques dans toutes les langues : le SEO
 * français du site n'est pas dupliqué ni dilué.
 *
 * Registered automatiquement par autoconfigure (services.yaml).
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    /** Locales supportées par l'application (route /locale/{locale} incluse). */
    public const LOCALES = ['fr', 'en'];

    /** Cookie longue durée : la langue survit à la session du visiteur. */
    public const COOKIE_NAME = 'gm_locale';

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $locale = null;

        // 1. Session (choix explicite du visiteur pendant sa visite)
        if ($request->hasSession()) {
            $locale = $request->getSession()->get('_locale');
        }

        // 2. Cookie (visites précédentes)
        $locale ??= $request->cookies->get(self::COOKIE_NAME);

        if (\in_array($locale, self::LOCALES, true)) {
            $request->setLocale($locale);
        }
        // Sinon : default_locale fr (framework.yaml/translation.yaml)
    }

    public static function getSubscribedEvents(): array
    {
        // Priorité 16 : après l'initialisation de la session (128),
        // avant le routage et les contrôleurs.
        return [KernelEvents::REQUEST => [['onKernelRequest', 16]]];
    }
}
