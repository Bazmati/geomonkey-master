<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\MembershipRepository;
use App\Service\ImageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenements', name: 'app_event_')]
class EventController extends AbstractController
{
    /**
     * Filtres disponibles pour la liste d'événements (mobile).
     */
    private const FILTERS = ['tous', 'a-venir', 'passes'];

    public function __construct(
        private MembershipRepository $membershipRepository,
        private ImageManager $imageManager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $filter = $request->query->get('filtre', 'tous');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'tous';
        }

        return $this->render('front/event/index.html.twig', [
            'events' => $eventRepository->findVisibleFor($this->isViewerActiveMember(), $filter),
            'filter' => $filter,
        ]);
    }

    /**
     * Feed JSON pour le calendrier FullCalendar (desktop).
     * Mêmes règles de visibilité que la liste : jamais d'événement
     * non publié ni réservé aux membres si l'internaute n'y a pas droit.
     */
    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(EventRepository $eventRepository): JsonResponse
    {
        $isActiveMember = $this->isViewerActiveMember();
        $now = time();

        $payload = [];
        foreach ($eventRepository->findVisibleFor($isActiveMember, 'tous') as $event) {
            $payload[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start' => $event->getStartDate()?->format(\DateTimeInterface::ATOM),
                'end' => $event->getEndDate()?->format(\DateTimeInterface::ATOM),
                'url' => $this->generateUrl('app_event_show', ['id' => $event->getId()]),
                'membersOnly' => (bool) $event->getIsMembersOnly(),
                'location' => $event->getLocation(),
                'past' => $event->getEndDate() !== null && $event->getEndDate()->getTimestamp() < $now,
            ];
        }

        return $this->json($payload);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id, EventRepository $eventRepository): Response
    {
        $event = $eventRepository->findOneVisibleBy($id, $this->isViewerActiveMember());

        if ($event === null) {
            throw $this->createNotFoundException("Cet événement n'existe pas ou n'est pas accessible.");
        }

        return $this->render('front/event/show.html.twig', [
            'event' => $event,
            'image_url' => $this->imageManager->getUrl($event, 'large'),
        ]);
    }

    /**
     * L'internaute courant est-il membre actif de l'association ?
     * (adhésion active ET non expirée)
     */
    private function isViewerActiveMember(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $this->membershipRepository->findLastActiveByUser($user);

        return $membership !== null && !$membership->isExpired();
    }
}
