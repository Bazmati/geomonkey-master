<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\MembershipRepository;
use App\Service\ImageManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenements', name: 'app_event_')]
class EventController extends AbstractController
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private ImageManager $imageManager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('front/event/index.html.twig', [
            'events' => $eventRepository->findVisibleFor($this->isViewerActiveMember()),
        ]);
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