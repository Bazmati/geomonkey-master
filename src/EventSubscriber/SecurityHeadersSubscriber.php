<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité appliqués à toutes les réponses HTML.
 * Corrige les alertes ZAP : X-Content-Type-Options, X-Frame-Options,
 * Permissions-Policy, COOP/CORP, Referrer-Policy, CSP.
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse', 0]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        // Anti-MIME-sniffing (ZAP 10021)
        $headers->set('X-Content-Type-Options', 'nosniff');

        // Anti-clickjacking (ZAP 10020), complete par CSP frame-ancestors
        $headers->set('X-Frame-Options', 'DENY');

        // Referents minimalistes
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (ZAP 10063) : on coupe tout ce qu'on n'utilise pas
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');

        // Isolation cross-origin (ZAP 90004)
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // HSTS : uniquement en prod, sur un domaine reel (jamais en local,
        // sinon le navigateur forcerait https sur 127.0.0.1 pendant un an)
        $request = $event->getRequest();
        $host = $request->getHost();
        $isLocal = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($this->environment === 'prod' && !$isLocal && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // CSP (ZAP 10038) : uniquement en prod, le profiler de dev
        // ne respecte pas une CSP stricte.
        if ($this->environment === 'prod') {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com",
                "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
                "font-src 'self' https://cdn.jsdelivr.net data:",
                "img-src 'self' data: blob:",
                "media-src 'self'",
                "connect-src 'self'",
                "frame-src https://challenges.cloudflare.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ]);
            $headers->set('Content-Security-Policy', $csp);
        }
    }
}
