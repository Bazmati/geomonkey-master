<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Enum\ProjectTaskState;
use App\Repository\ProjectRepository;
use App\Repository\ProjectTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projets', name: 'app_project_')]
class ProjectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        return $this->render('front/project/index.html.twig', [
            'projects' => $projectRepository->findPublished(),
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(string $slug, ProjectRepository $projectRepository): Response
    {
        $project = $projectRepository->findOnePublishedBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException("Ce projet n'existe pas ou n'est pas accessible.");
        }

        return $this->render('front/project/show.html.twig', [
            'project' => $project,
        ]);
    }

    /**
     * Tableau de suivi d'un projet (interne : tout utilisateur connecté peut
     * voir l'avancement ; le public ne voit que le statut sur la page projet).
     */
    #[Route('/{slug}/avancement', name: 'board', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function board(string $slug, ProjectRepository $projectRepository): Response
    {
        $project = $projectRepository->findOnePublishedBySlug($slug);

        if ($project === null) {
            throw $this->createNotFoundException("Ce projet n'existe pas ou n'est pas accessible.");
        }

        /** @var User|null $user */
        $user = $this->getUser();

        return $this->render('front/project/board.html.twig', [
            'project' => $project,
            'is_leader' => null !== $user && $project->getLeader() === $user,
        ]);
    }

    /**
     * Change l'état d'une tâche — réservé au chef de projet et au
     * responsable désigné de la tâche (avec jeton CSRF).
     */
    #[Route('/{slug}/task/{task}/state', name: 'task_state', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function taskState(
        Request $request,
        string $slug,
        int $task,
        ProjectTaskRepository $taskRepository
    ): Response {
        // Résolution manuelle (pas de MapEntity) : recherche par identifiant
        $task = $taskRepository->find($task);
        if ($task === null) {
            throw $this->createNotFoundException('Tâche introuvable.');
        }

        $project = $task->getProject();

        if ($project === null || $project->getSlug() !== $slug || !$project->isPublished()) {
            throw $this->createNotFoundException("Ce projet n'existe pas ou n'est pas accessible.");
        }

        $redirect = $this->redirectToRoute('app_project_board', ['slug' => $slug]);

        /** @var User $user */
        $user = $this->getUser();

        // Seuls le chef de projet et le responsable de la tâche font avancer
        if ($project->getLeader() !== $user && $task->getAssignee() !== $user) {
            $this->addFlash('warning', 'Seuls le chef de projet et le responsable de la tâche peuvent changer son état.');

            return $redirect;
        }

        if (!$this->isCsrfTokenValid('task-state' . $task->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide, réessayez.');

            return $redirect;
        }

        $state = ProjectTaskState::tryFrom($request->getPayload()->getString('state'));
        if ($state === null) {
            $this->addFlash('danger', 'État invalide.');

            return $redirect;
        }

        $task->setState($state);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Tâche « %s » : %s.', $task->getTitle(), $state->getLabel()));

        return $redirect;
    }
}
