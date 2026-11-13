<?php

namespace App\Controller\Admin;

use App\Repository\EventRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
    ) {}

    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        $stats = [
            'events' => $this->eventRepository->count([]),
            'projects' => $this->projectRepository->count([]),
            'users' => $this->userRepository->count([]),
        ];

        // Prochains événements, formatés pour le template existant
        $upcomingEvents = [];
        foreach ($this->eventRepository->findUpcoming(5) as $event) {
            $upcomingEvents[] = [
                'title' => $event->getTitle(),
                'date' => $event->getStartDate()?->format('d/m/Y H:i'),
                'location' => $event->getLocation(),
            ];
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'upcomingEvents' => $upcomingEvents,
        ]);
    }
}
