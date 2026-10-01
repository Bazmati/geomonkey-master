<?php

namespace App\Tests\EventListener;

use App\Entity\User;
use App\Enum\OfficeFunction;
use App\EventListener\SecurityExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class SecurityExceptionListenerTest extends TestCase
{
    private SecurityExceptionListener $listener;
    private UrlGeneratorInterface $urlGenerator;
    private TokenStorageInterface $tokenStorage;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->urlGenerator->method('generate')
            ->with('app_access_denied')
            ->willReturn('/access-denied');

        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        
        $this->listener = new SecurityExceptionListener($this->urlGenerator, $this->tokenStorage);
    }

    public function testOnKernelExceptionWithNoneUserRedirectsToAccessDenied(): void
    {
        // Créer un mock de user avec OfficeFunction::None
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);
        $user->setEmail('visitor@test.com');

        // Créer un mock de TokenInterface
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Configurer le TokenStorage pour retourner notre token
        $this->tokenStorage->method('getToken')->willReturn($token);

        // Créer un mock de Request
        $request = $this->createMock(Request::class);

        // Créer l'exception
        $exception = new AccessDeniedHttpException('Access Denied');

        // Créer un HttpKernel mock
        $httpKernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);

        // Créer l'event réel
        $event = new ExceptionEvent($httpKernel, $request, 1, $exception);

        // Appel de la méthode
        $this->listener->onKernelException($event);

        // Vérifier que la réponse a été définie
        $response = $event->getResponse();
        $this->assertNotNull($response);
        
        // Vérifier que c'est une redirection
        $this->assertSame(302, $response->getStatusCode());
        
        // Vérifier l'URL de redirection
        $this->assertStringContainsString('/access-denied', $response->getTargetUrl());
    }

    public function testOnKernelExceptionWithOtherUserDoesNotRedirect(): void
    {
        // Créer un mock de user avec OfficeFunction::ActiveMember
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $user->setEmail('member@test.com');

        // Créer un mock de TokenInterface
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Configurer le TokenStorage pour retourner notre token
        $this->tokenStorage->method('getToken')->willReturn($token);

        // Créer un mock de Request
        $request = $this->createMock(Request::class);

        // Créer l'exception
        $exception = new AccessDeniedHttpException('Access Denied');

        // Créer un HttpKernel mock
        $httpKernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);

        // Créer l'event réel
        $event = new ExceptionEvent($httpKernel, $request, 1, $exception);

        // Appel de la méthode
        $this->listener->onKernelException($event);

        // Vérifier que la réponse n'a PAS été définie (donc pas de redirection)
        $response = $event->getResponse();
        $this->assertNull($response);
    }

    public function testOnKernelExceptionWithNonAccessDeniedExceptionDoesNothing(): void
    {
        // Créer un mock de user
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);

        // Créer un mock de TokenInterface
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Configurer le TokenStorage pour retourner notre token
        $this->tokenStorage->method('getToken')->willReturn($token);

        // Créer un mock de Request
        $request = $this->createMock(Request::class);

        // Créer une exception qui n'est pas AccessDenied
        $exception = new \Exception('Some other error');

        // Créer un HttpKernel mock
        $httpKernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);

        // Créer l'event réel
        $event = new ExceptionEvent($httpKernel, $request, 1, $exception);

        // Appel de la méthode
        $this->listener->onKernelException($event);

        // Vérifier que la réponse n'a PAS été définie
        $response = $event->getResponse();
        $this->assertNull($response);
    }

    public function testOnKernelExceptionWithAnonymousUserDoesNothing(): void
    {
        // Configurer le TokenStorage pour retourner null (pas de token)
        $this->tokenStorage->method('getToken')->willReturn(null);

        // Créer un mock de Request
        $request = $this->createMock(Request::class);

        // Créer l'exception
        $exception = new AccessDeniedHttpException('Access Denied');

        // Créer un HttpKernel mock
        $httpKernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);

        // Créer l'event réel
        $event = new ExceptionEvent($httpKernel, $request, 1, $exception);

        // Appel de la méthode
        $this->listener->onKernelException($event);

        // Vérifier que la réponse n'a PAS été définie
        $response = $event->getResponse();
        $this->assertNull($response);
    }

    public function testOnKernelExceptionWithTokenButNullUserDoesNothing(): void
    {
        // Créer un mock de TokenInterface avec null user
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        // Configurer le TokenStorage pour retourner notre token
        $this->tokenStorage->method('getToken')->willReturn($token);

        // Créer un mock de Request
        $request = $this->createMock(Request::class);

        // Créer l'exception
        $exception = new AccessDeniedHttpException('Access Denied');

        // Créer un HttpKernel mock
        $httpKernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);

        // Créer l'event réel
        $event = new ExceptionEvent($httpKernel, $request, 1, $exception);

        // Appel de la méthode
        $this->listener->onKernelException($event);

        // Vérifier que la réponse n'a PAS été définie
        $response = $event->getResponse();
        $this->assertNull($response);
    }
}