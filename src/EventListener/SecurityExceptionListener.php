<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Intercepte les erreurs d'accès refusé pour fournir une meilleure UX.
 * Spécialement pour les utilisateurs avec OfficeFunction::None qui voient un message clair.
 */
class SecurityExceptionListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Vérifier si c'est une erreur d'accès refusé
        if (!$exception instanceof AccessDeniedHttpException && 
            !$exception instanceof AccessDeniedException) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        
        // Si l'utilisateur est connecté mais a OfficeFunction::None, rediriger vers la page access_denied
        if ($user instanceof User && $user->getOfficeFunction() === OfficeFunction::None) {
            $response = new RedirectResponse(
                $this->urlGenerator->generate('app_access_denied')
            );
            $event->setResponse($response);
        }
    }
}