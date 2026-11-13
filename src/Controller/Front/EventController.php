<?php

namespace App\Controller\Front;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Enum\ParticipantRole;
use App\Entity\User;
use App\Repository\EventParticipantRepository;
use App\Repository\EventRepository;
use App\Repository\MembershipRepository;
use App\Service\EventIcsGenerator;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/evenements', name: 'app_event_')]
class EventController extends AbstractController
{
    private const FILTERS = ['tous', 'a-venir', 'passes'];

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly ImageManager $imageManager,
        private readonly EventParticipantRepository $participantRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventIcsGenerator $icsGenerator,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filter = $request->query->get('filter', 'tous');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'tous';
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = null !== $user && $this->membershipRepository->hasActiveMembership($user);

        $events = $this->entityManager->getRepository(Event::class)
            ->findVisibleFor($isActiveMember, $filter);

        $imageManager = $this->imageManager;

        return $this->render('front/event/index.html.twig', [
            'events' => $events,
            'filter' => $filter,
            'image_helper' => fn (Event $event) => $imageManager->getEventImage($event),
        ]);
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = null !== $user && $this->membershipRepository->hasActiveMembership($user);

        $events = $this->entityManager->getRepository(Event::class)
            ->findVisibleFor($isActiveMember);

        $data = [];
        foreach ($events as $event) {
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start' => $event->getStartDate()?->format(DATE_ATOM),
                'end' => $event->getEndDate()?->format(DATE_ATOM),
                'url' => $this->generateUrl('app_event_show', ['id' => $event->getId()]),
                'membersOnly' => $event->isMembersOnly(),
                'location' => $event->getLocation(),
                'past' => $event->getStartDate() !== null && $event->getStartDate()->getTimestamp() < time(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $event = $this->findVisibleEventOr404($id);

        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = $this->isViewerActiveMember();

        $participantCount = $this->participantRepository->countParticipants($event);
        $capacity = $event->getCapacity();

        return $this->render('front/event/show.html.twig', [
            'event' => $event,
            'is_active_member' => $isActiveMember,
            'isRegistered' => null !== $user && $this->participantRepository->userParticipatesInEvent($user, $event),
            'participantCount' => $participantCount,
            'isPast' => $event->getStartDate() !== null && $event->getStartDate()->getTimestamp() < time(),
            'isFull' => null !== $capacity && $participantCount >= $capacity,
        ]);
    }

    #[Route('/{id}/register', name: 'register', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function register(Request $request, int $id): Response
    {
        $event = $this->findVisibleEventOr404($id);

        /** @var User|null $user */
        $user = $this->getUser();
        if (null === $user) {
            $this->addFlash('warning', 'Connectez-vous pour participer à un événement.');

            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('register' . $event->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide, réessayez.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Événement réservé aux membres : adhésion active obligatoire
        if ($event->isMembersOnly() && !$this->isViewerActiveMember()) {
            $this->addFlash('warning', 'Cet événement est réservé aux membres actifs de l\'association.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Événement déjà commencé
        if ($event->getStartDate() !== null && $event->getStartDate()->getTimestamp() < time()) {
            $this->addFlash('warning', 'Cet événement a déjà commencé, les inscriptions sont fermées.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Capacité atteinte
        if (null !== $event->getCapacity()
            && $this->participantRepository->countParticipants($event) >= $event->getCapacity()) {
            $this->addFlash('warning', 'Toutes les places sont prises, l\'événement est complet.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        // Déjà inscrit
        if ($this->participantRepository->userParticipatesInEvent($user, $event)) {
            $this->addFlash('info', 'Vous êtes déjà inscrit à cet événement.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $participant = new EventParticipant();
        $participant->setEvent($event)
            ->setUser($user)
            ->setRole(ParticipantRole::Participant);

        $this->entityManager->persist($participant);
        $this->entityManager->flush();

        $this->addFlash('success', 'Inscription confirmée, à bientôt !');

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}/unregister', name: 'unregister', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unregister(Request $request, int $id): Response
    {
        $event = $this->findVisibleEventOr404($id);

        /** @var User|null $user */
        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('unregister' . $event->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide, réessayez.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $participant = $this->participantRepository->findOneBy(['event' => $event, 'user' => $user]);
        if (null === $participant) {
            $this->addFlash('info', 'Vous n\'êtes pas inscrit à cet événement.');

            return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
        }

        $this->entityManager->remove($participant);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre inscription a été annulée.');

        return $this->redirectToRoute('app_event_show', ['id' => $event->getId()]);
    }

    #[Route('/{id}.ics', name: 'ics', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function ics(int $id): Response
    {
        $event = $this->findVisibleEventOr404($id);

        $url = $this->generateUrl('app_event_show', ['id' => $event->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->icsGenerator->response($event, $url);
    }

    /**
     * Retrouve un événement publié visible pour l'utilisateur courant, sinon 404.
     */
    private function findVisibleEventOr404(int $id): Event
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = null !== $user && $this->membershipRepository->hasActiveMembership($user);

        $event = $this->entityManager->getRepository(Event::class)
            ->findOneVisibleBy($id, $isActiveMember);

        if (null === $event) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        return $event;
    }

    private function isViewerActiveMember(): bool
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return null !== $user && $this->membershipRepository->hasActiveMembership($user);
    }
}
