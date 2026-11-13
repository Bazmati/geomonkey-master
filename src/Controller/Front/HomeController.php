<?php

namespace App\Controller\Front;

use App\Enum\ProjectStatus;
use App\Repository\BlogPostRepository;
use App\Repository\EventRepository;
use App\Repository\GalleryImageRepository;
use App\Repository\MembershipRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private ProjectRepository $projectRepository,
        private BlogPostRepository $blogPostRepository,
        private GalleryImageRepository $galleryImageRepository,
        private MembershipRepository $membershipRepository,
        private UserRepository $userRepository,
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();
        $isActiveMember = null !== $user && $this->membershipRepository->hasActiveMembership($user);

        // Prochains événements visibles (membres actifs voient aussi les membres-only)
        $upcomingEvents = $this->eventRepository->findUpcomingVisibleFor($isActiveMember, 3);

        // Projets publiés non terminés (en préparation ou en cours)
        $ongoingProjects = array_values(array_filter(
            $this->projectRepository->findPublished(),
            static fn ($project) => ProjectStatus::Completed !== $project->getStatus(),
        ));
        $ongoingProjects = \array_slice($ongoingProjects, 0, 3);

        // Dernières actualités publiées
        $recentPosts = $this->blogPostRepository->findRecentPosts(3);

        // Mosaïque galerie : images publiques visibles (selon le statut du visiteur)
        $galleryImages = \array_slice($this->galleryImageRepository->findVisibleForUser($user), 0, 6);

        // Chiffres clés
        $stats = [
            'events' => $this->eventRepository->count(['isPublished' => true]),
            'projects' => $this->projectRepository->count(['isPublished' => true]),
            'members' => $this->userRepository->count([]),
            'posts' => $this->blogPostRepository->count(['isPublished' => true]),
        ];

        return $this->render('front/home/index.html.twig', [
            'upcomingEvents' => $upcomingEvents,
            'ongoingProjects' => $ongoingProjects,
            'recentPosts' => $recentPosts,
            'galleryImages' => $galleryImages,
            'stats' => $stats,
        ]);
    }
}
