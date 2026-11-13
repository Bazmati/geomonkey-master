<?php

namespace App\Controller\Admin;

use App\Repository\BlogPostRepository;
use App\Repository\EventRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Recherche globale back-office (Ctrl+K).
 * Retourne du JSON, jamais du HTML : le rendu vit dans le contrôleur Stimulus.
 */
#[Route('/admin/search', name: 'app_admin_search', methods: ['GET'])]
class SearchController extends AbstractController
{
    private const MIN_LENGTH = 2;
    private const MAX_PER_TYPE = 5;

    #[IsGranted('ENTITY_VIEW')]
    public function __invoke(
        Request $request,
        UserRepository $users,
        EventRepository $events,
        BlogPostRepository $posts,
        ProjectRepository $projects,
    ): JsonResponse {
        $q = trim((string) $request->query->get('q', ''));

        if (mb_strlen($q) < self::MIN_LENGTH) {
            return $this->json(['groups' => []]);
        }

        // LIKE échappé : %, _ et \ neutralisés pour éviter les jokers involontaires
        $like = '%' . addcslashes(mb_strtolower($q), '%_\\') . '%';

        $userRows = $users->createQueryBuilder('u')
            ->where('LOWER(CONCAT(u.firstName, \' \', u.lastName)) LIKE :q OR LOWER(u.email) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('u.lastName', 'ASC')
            ->setMaxResults(self::MAX_PER_TYPE)
            ->getQuery()
            ->getResult();

        $eventRows = $events->createQueryBuilder('e')
            ->where('LOWER(e.title) LIKE :q OR LOWER(e.location) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('e.startDate', 'DESC')
            ->setMaxResults(self::MAX_PER_TYPE)
            ->getQuery()
            ->getResult();

        $postRows = $posts->createQueryBuilder('p')
            ->where('LOWER(p.title) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(self::MAX_PER_TYPE)
            ->getQuery()
            ->getResult();

        $projectRows = $projects->createQueryBuilder('pr')
            ->where('LOWER(pr.title) LIKE :q')
            ->setParameter('q', $like)
            ->orderBy('pr.id', 'DESC')
            ->setMaxResults(self::MAX_PER_TYPE)
            ->getQuery()
            ->getResult();

        $groups = [];

        $userItems = array_map(fn ($u) => [
            'title' => $u->getLastName() . ' ' . $u->getFirstName(),
            'subtitle' => $u->getEmail(),
            'url' => $this->generateUrl('app_admin_user_edit', ['id' => $u->getId()]),
        ], $userRows);
        if ($userItems) {
            $groups[] = ['label' => 'Utilisateurs', 'items' => $userItems];
        }

        $eventItems = array_map(fn ($e) => [
            'title' => $e->getTitle(),
            'subtitle' => $e->getStartDate()?->format('d/m/Y') ?? '',
            'url' => $this->generateUrl('admin_events_edit', ['id' => $e->getId()]),
        ], $eventRows);
        if ($eventItems) {
            $groups[] = ['label' => 'Événements', 'items' => $eventItems];
        }

        $postItems = array_map(fn ($p) => [
            'title' => $p->getTitle(),
            'subtitle' => $p->isPublished() ? 'publié' : 'brouillon',
            'url' => $this->generateUrl('app_admin_blog_edit', ['id' => $p->getId()]),
        ], $postRows);
        if ($postItems) {
            $groups[] = ['label' => 'Articles', 'items' => $postItems];
        }

        $projectItems = array_map(fn ($pr) => [
            'title' => $pr->getTitle(),
            'subtitle' => $pr->getStatus()->getLabel() . ($pr->isPublished() ? '' : ' · brouillon'),
            'url' => $this->generateUrl('admin_projects_edit', ['id' => $pr->getId()]),
        ], $projectRows);
        if ($projectItems) {
            $groups[] = ['label' => 'Projets', 'items' => $projectItems];
        }

        return $this->json(['groups' => $groups]);
    }
}