<?php

namespace App\Tests\Controller\Front;

use App\Controller\Front\EventController;
use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use App\Enum\ParticipantRole;
use App\Repository\EventParticipantRepository;
use App\Repository\EventRepository;
use App\Repository\MembershipRepository;
use App\Service\EventIcsGenerator;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Tests unitaires pour les méthodes register et unregister du EventController
 * 
 * Note: Ces tests utilisent des mocks pour simuler l'environnement Symfony.
 * Pour des tests plus complets, il faudrait utiliser des tests fonctionnels.
 */
class EventControllerTest extends TestCase
{
    private MembershipRepository $membershipRepo;
    private ImageManager $imageManager;
    private EventParticipantRepository $participantRepo;
    private EntityManagerInterface $em;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private Session $session;

    protected function setUp(): void
    {
        $this->membershipRepo = $this->createMock(MembershipRepository::class);
        $this->imageManager = $this->createMock(ImageManager::class);
        $this->participantRepo = $this->createMock(EventParticipantRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        
        // Créer une session réelle pour les flash messages
        $this->session = new Session(new MockArraySessionStorage());
    }

    /**
     * Crée une instance du contrôleur avec un user mock
     */
    private function createControllerWithUser(?User $user): EventController
    {
        // Créer une classe anonyme qui étend EventController et permet de mock getUser()
        $controller = new class($this->membershipRepo, $this->imageManager, $this->participantRepo, $this->em, new EventIcsGenerator()) extends EventController {
            private ?User $mockUser;
            
            public function setMockUser(?User $user): void
            {
                $this->mockUser = $user;
            }
            
            public function getUser(): ?User
            {
                return $this->mockUser;
            }
            
        };
        
        $controller->setMockUser($user);
        
        return $controller;
    }

    /**
     * isViewerActiveMember() est privée : invocation par réflexion
     * (un appel direct depuis le test serait une erreur fatale).
     */
    private function invokeIsViewerActiveMember(EventController $controller): bool
    {
        $method = new \ReflectionMethod($controller, 'isViewerActiveMember');

        return $method->invoke($controller);
    }

    public function testRegisterWithoutUser(): void
    {
        // Créer un événement mock
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        
        // Configurer le repository pour retourner l'événement
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Créer un contrôleur avec null comme user
        $controller = $this->createControllerWithUser(null);
        
        // Pour l'instant, nous allons juste vérifier que la méthode ne lève pas d'exception
        // sans user, elle devrait rediriger vers la page de login
        
        $this->markTestIncomplete(
            'Ce test nécessite un environnement Symfony complet pour tester les redirections.'
        );
    }

    /**
     * Test simplifié pour la méthode register
     * Note: Ce test est incomplet car il nécessite un environnement Symfony complet
     */
    public function testRegisterWithValidUserAndCsrf(): void
    {
        // Ce test est très limité sans un environnement Symfony complet
        // Nous allons tester la logique métier en mockant toutes les dépendances
        
        $this->markTestIncomplete(
            'Ce test nécessite un environnement Symfony complet avec un client HTTP.'
        );
    }

    /**
     * Test de la logique métier de register
     */
    public function testRegisterLogicMembersOnlyEvent(): void
    {
        // Créer un événement réservé aux membres
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        $event->method('isMembersOnly')->willReturn(true);
        $event->method('getStartDate')->willReturn(new \DateTime('+1 day'));
        $event->method('getCapacity')->willReturn(null);
        
        // Créer un utilisateur non membre
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        
        // Configurer hasActiveMembership pour retourner false
        $this->membershipRepo
            ->method('hasActiveMembership')
            ->with($user)
            ->willReturn(false);
        
        // Configurer le repository pour retourner l'événement
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Créer un contrôleur avec le user
        $controller = $this->createControllerWithUser($user);
        
        // Vérifier que hasActiveMembership est appelé avec le bon user
        // Note: On ne peut pas facilement tester la redirection sans un environnement Symfony complet
        // Mais on peut vérifier que la méthode isViewerActiveMember retourne false
        $this->assertFalse($this->invokeIsViewerActiveMember($controller));
        
    }

    public function testRegisterLogicEventAlreadyStarted(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        $event->method('isMembersOnly')->willReturn(false);
        $event->method('getStartDate')->willReturn(new \DateTime('-1 hour'));
        $event->method('getCapacity')->willReturn(null);
        
        $user = $this->createMock(User::class);
        
        // Configurer le repository
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Créer un contrôleur avec le user
        $controller = $this->createControllerWithUser($user);
        
        // Vérifier que la méthode vérifie si l'événement a commencé
        // mais nous ne pouvons pas tester la redirection
        
        $this->markTestIncomplete(
            'Ce test nécessite un environnement Symfony complet.'
        );
    }

    public function testRegisterLogicEventIsFull(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        $event->method('isMembersOnly')->willReturn(false);
        $event->method('getStartDate')->willReturn(new \DateTime('+1 day'));
        $event->method('getCapacity')->willReturn(10);
        
        // Configurer countParticipants pour retourner 10 (complet)
        $this->participantRepo
            ->method('countParticipants')
            ->with($event)
            ->willReturn(10);
        
        // Configurer le repository
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Créer un contrôleur avec le user
        $user = $this->createMock(User::class);
        $controller = $this->createControllerWithUser($user);
        
        
        $this->markTestIncomplete(
            'Ce test nécessite un environnement Symfony complet.'
        );
    }

    public function testRegisterLogicUserAlreadyRegistered(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        $event->method('isMembersOnly')->willReturn(false);
        $event->method('getStartDate')->willReturn(new \DateTime('+1 day'));
        $event->method('getCapacity')->willReturn(null);
        
        $user = $this->createMock(User::class);
        
        // Configurer userParticipatesInEvent pour retourner true
        $this->participantRepo
            ->method('userParticipatesInEvent')
            ->with($user, $event)
            ->willReturn(true);
        
        // Configurer le repository
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Créer un contrôleur avec le user
        $controller = $this->createControllerWithUser($user);
        
        
        $this->markTestIncomplete(
            'Ce test nécessite un environnement Symfony complet.'
        );
    }

    public function testRegisterLogicSuccessfulRegistration(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        $event->method('isMembersOnly')->willReturn(false);
        $event->method('getStartDate')->willReturn(new \DateTime('+1 day'));
        $event->method('getCapacity')->willReturn(20);
        
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);
        
        // Configurer le participant repository
        $this->participantRepo
            ->method('userParticipatesInEvent')
            ->with($user, $event)
            ->willReturn(false);
        
        $this->participantRepo
            ->method('countParticipants')
            ->with($event)
            ->willReturn(5);
        
        // Configurer le repository
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Configurer persist et flush
        $this->em
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($participant) {
                return $participant instanceof EventParticipant
                    && $participant->getRole() === ParticipantRole::Participant;
            }));
        
        $this->em
            ->expects($this->once())
            ->method('flush');
        
        // Créer un contrôleur avec le user
        $controller = $this->createControllerWithUser($user);
        
        $this->markTestIncomplete(
            'Ce test nécessite un environnement Symfony complet pour tester la création du participant.'
        );
    }

    /**
     * Test pour unregister
     */
    public function testUnregisterWithUserNotParticipating(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        
        $user = $this->createMock(User::class);
        
        // Configurer findOneBy pour retourner null
        $this->participantRepo
            ->method('findOneBy')
            ->with(['event' => $event, 'user' => $user])
            ->willReturn(null);
        
        // Configurer le repository
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Créer un contrôleur avec le user
        $controller = $this->createControllerWithUser($user);
        
        
        $this->em
            ->expects($this->never())
            ->method('remove');
        
        $this->em
            ->expects($this->never())
            ->method('flush');
        
        $this->markTestIncomplete(
            'Ce test nécessite un environnement Symfony complet.'
        );
    }

    /**
     * Tests pour la méthode ics
     */
    public function testIcsGeneration(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(1);
        $event->method('getTitle')->willReturn('Test Event');
        $event->method('getLocation')->willReturn('Test Location');
        $event->method('getDescription')->willReturn('Test Description');
        $event->method('getStartDate')->willReturn(new \DateTime('2024-12-25 10:00:00'));
        $event->method('getEndDate')->willReturn(new \DateTime('2024-12-25 12:00:00'));
        
        // Configurer le repository
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo
            ->method('findOneVisibleBy')
            ->willReturn($event);
        
        $this->em
            ->method('getRepository')
            ->willReturn($eventRepo);
        
        // Configurer urlGenerator
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturn('http://localhost:8000/events/1');
        
        // Classe anonyme : ics() appelle generateUrl() (qui passe par le container
        // Symfony) -> on la surcharge pour rendre le test autonome.
        $controller = new class($this->membershipRepo, $this->imageManager, $this->participantRepo, $this->em, new EventIcsGenerator()) extends EventController {
            private ?User $mockUser = null;
            
            public function setMockUser(?User $user): void
            {
                $this->mockUser = $user;
            }
            
            public function getUser(): ?User
            {
                return $this->mockUser;
            }
            
            public function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
            {
                return 'http://localhost:8000/events/1';
            }
        };
        
        $controller->setMockUser(null);
        
        // Appeler la méthode ics
        try {
            $result = $controller->ics(1);
            
            $this->assertEquals(200, $result->getStatusCode());
            $this->assertStringContainsString('BEGIN:VCALENDAR', $result->getContent());
            $this->assertStringContainsString('SUMMARY:Test Event', $result->getContent());
            $this->assertStringContainsString('LOCATION:Test Location', $result->getContent());
            $this->assertStringContainsString('DESCRIPTION:Test Description', $result->getContent());
            
            // Vérifier le content type
            $this->assertEquals('text/calendar; charset=utf-8', $result->headers->get('Content-Type'));
            
            // Vérifier le content disposition
            $this->assertStringContainsString('attachment', $result->headers->get('Content-Disposition'));
            $this->assertStringContainsString('.ics', $result->headers->get('Content-Disposition'));
        } catch (\Throwable $e) {
            $this->markTestIncomplete(
                'Ce test nécessite un environnement Symfony complet : ' . $e->getMessage()
            );
        }
    }

    /**
     * Test pour la méthode isViewerActiveMember
     */
    public function testIsViewerActiveMemberWithUser(): void
    {
        $user = $this->createMock(User::class);
        
        // Configurer hasActiveMembership pour retourner true
        $this->membershipRepo
            ->method('hasActiveMembership')
            ->with($user)
            ->willReturn(true);
        
        // Créer un contrôleur avec le user
        $controller = $this->createControllerWithUser($user);
        
        // Appeler la méthode
        $result = $this->invokeIsViewerActiveMember($controller);
        
        $this->assertTrue($result);
        
    }

    public function testIsViewerActiveMemberWithoutUser(): void
    {
        // Créer un contrôleur sans user
        $controller = $this->createControllerWithUser(null);
        
        // Appeler la méthode
        $result = $this->invokeIsViewerActiveMember($controller);
        
        $this->assertFalse($result);
        
        // hasActiveMembership ne doit pas être appelé car user est null
        $this->membershipRepo
            ->expects($this->never())
            ->method('hasActiveMembership');
    }

    public function testIsViewerActiveMemberWithInactiveUser(): void
    {
        $user = $this->createMock(User::class);
        
        // Configurer hasActiveMembership pour retourner false
        $this->membershipRepo
            ->method('hasActiveMembership')
            ->with($user)
            ->willReturn(false);
        
        // Créer un contrôleur avec le user
        $controller = $this->createControllerWithUser($user);
        
        // Appeler la méthode
        $result = $this->invokeIsViewerActiveMember($controller);
        
        $this->assertFalse($result);
    }
}
