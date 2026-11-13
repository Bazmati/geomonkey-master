<?php

namespace App\Controller\Admin;

use App\Entity\BlogPost;
use App\Entity\Project;
use App\Repository\BlogPostRepository;
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
        private BlogPostRepository $blogPostRepository,
    ) {}

    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();

        // Demandes d'adhésion en attente de validation
        $pendingMembers = $this->userRepository->findPendingMembershipRequests();

        // Brouillons d'actualités : ceux de l'utilisateur connecté d'abord
        $draftPosts = $this->blogPostRepository->findDrafts(5);
        usort($draftPosts, static fn (BlogPost $a, BlogPost $b) => (int) ($b->getAuthor() === $user) - (int) ($a->getAuthor() === $user));

        // Projets en cours : ceux organisés par l'utilisateur connecté d'abord
        $ongoingProjects = $this->projectRepository->findOngoing(6);
        usort($ongoingProjects, static fn (Project $a, Project $b) => (int) ($b->getLeader() === $user) - (int) ($a->getLeader() === $user));

        $stats = [
            'events' => $this->eventRepository->count([]),
            'projects' => $this->projectRepository->count([]),
            'users' => $this->userRepository->count([]),
            'pendingMembers' => \count($pendingMembers),
            'draftPosts' => \count($draftPosts),
            'ongoingProjects' => \count($ongoingProjects),
        ];

        return $this->render('admin/dashboard/index.html.twig', [
            'stats' => $stats,
            'upcomingEvents' => $this->eventRepository->findUpcoming(5),
            'pendingMembers' => $pendingMembers,
            'draftPosts' => $draftPosts,
            'ongoingProjects' => $ongoingProjects,
        ]);
    }
}
