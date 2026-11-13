<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Entity\ProjectTask;
use App\Entity\ProjectTaskDocument;
use App\Form\ProjectTaskType;
use App\Form\ProjectType;
use App\Repository\ProjectTaskRepository;
use App\Repository\ProjectRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/project')]
final class ProjectController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }
    #[Route(name: 'admin_projects_index', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function index(ProjectRepository $projectRepository): Response
    {
        return $this->render('admin/project/index.html.twig', [
            'projects' => $projectRepository->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_projects_new', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_CREATE')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->computeSlug();

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageManager->upload($project, $imageFile);
            }

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été créé avec succès.');

            return $this->redirectToRoute('admin_projects_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_projects_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_EDIT', 'project')]
    public function edit(
        Request $request,
        Project $project,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageManager->upload($project, $imageFile);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été modifié avec succès.');

            return $this->redirectToRoute('admin_projects_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_projects_delete', methods: ['POST'])]
    #[IsGranted('ENTITY_DELETE', 'project')]
    public function delete(
        Request $request,
        Project $project,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$project->getId(), $request->getPayload()->getString('_token'))) {
            $imageManager->deleteForEntity($project);

            $entityManager->remove($project);
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_projects_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Mise en ligne / retrait — président uniquement (pattern BlogPost/Event).
     */
    #[Route('/{id}/toggle-publish', name: 'admin_projects_toggle_publish', methods: ['POST'])]
    #[IsGranted('ENTITY_PUBLISH', 'project')]
    public function togglePublish(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('publish'.$project->getId(), $request->getPayload()->getString('_token'))) {
            $publish = !$project->isPublished();
            $project->setIsPublished($publish);
            $entityManager->flush();

            $this->addFlash('success', $publish
                ? 'Projet mis en ligne : visible sur le site public.'
                : 'Projet retiré de la ligne (il reste en brouillon).');
        }

        return $this->redirectToRoute('admin_projects_index', [], Response::HTTP_SEE_OTHER);
    }

    // =========================================================
    // TÂCHES (checklist d'avancement du projet, suivi interne)
    // =========================================================

    /**
     * Gestion des tâches d'un projet (création, édition, pointage).
     */
    #[Route('/{id}/tasks', name: 'admin_projects_tasks', methods: ['GET'])]
    #[IsGranted('ENTITY_EDIT', 'project')]
    public function tasks(Project $project): Response
    {
        return $this->render('admin/project/tasks.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/tasks/new', name: 'admin_projects_task_new', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_EDIT', 'project')]
    public function taskNew(
        Request $request,
        Project $project,
        EntityManagerInterface $entityManager,
        ProjectTaskRepository $taskRepository,
        SluggerInterface $slugger
    ): Response {
        $task = new ProjectTask();
        $task->setProject($project);
        $form = $this->createForm(ProjectTaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setPosition($taskRepository->getNextPosition($project->getId()));

            $entityManager->persist($task);
            $entityManager->flush();

            $this->uploadTaskDocuments($task, $form->get('newDocuments')->getData(), $slugger, $entityManager);
            $entityManager->flush();

            $this->addFlash('success', 'La tâche a été ajoutée au projet.');

            return $this->redirectToRoute('admin_projects_tasks', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/project/task_form.html.twig', [
            'project' => $project,
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/task/{taskId}/edit', name: 'admin_projects_task_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_EDIT', 'task')]
    public function taskEdit(
        Request $request,
        ProjectTask $task,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $form = $this->createForm(ProjectTaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->uploadTaskDocuments($task, $form->get('newDocuments')->getData(), $slugger, $entityManager);
            $entityManager->flush();

            $this->addFlash('success', 'La tâche a été modifiée.');

            return $this->redirectToRoute('admin_projects_tasks', ['id' => $task->getProject()->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/project/task_form.html.twig', [
            'project' => $task->getProject(),
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/task/{taskId}', name: 'admin_projects_task_delete', methods: ['POST'])]
    #[IsGranted('ENTITY_EDIT', 'task')]
    public function taskDelete(
        Request $request,
        ProjectTask $task,
        EntityManagerInterface $entityManager
    ): Response {
        $projectId = $task->getProject()->getId();

        if ($this->isCsrfTokenValid('task-delete' . $task->getId(), $request->getPayload()->getString('_token'))) {
            $this->deleteTaskFiles($task);

            $entityManager->remove($task);
            $entityManager->flush();

            $this->addFlash('success', 'La tâche a été supprimée.');
        }

        return $this->redirectToRoute('admin_projects_tasks', ['id' => $projectId], Response::HTTP_SEE_OTHER);
    }

    /**
     * Retire un document uploadé d'une tâche (fichier + entité).
     */
    #[Route('/task-document/{document}', name: 'admin_projects_task_document_delete', methods: ['POST'])]
    #[IsGranted('ENTITY_EDIT', 'document')]
    public function taskDocumentDelete(
        Request $request,
        ProjectTaskDocument $document,
        EntityManagerInterface $entityManager
    ): Response {
        $projectId = $document->getTask()->getProject()->getId();

        if ($this->isCsrfTokenValid('task-doc-delete' . $document->getId(), $request->getPayload()->getString('_token'))) {
            $this->deleteTaskFile($document);

            $entityManager->remove($document);
            $entityManager->flush();

            $this->addFlash('success', 'Le document a été retiré de la tâche.');
        }

        return $this->redirectToRoute('admin_projects_tasks', ['id' => $projectId], Response::HTTP_SEE_OTHER);
    }

    /**
     * Stocke les fichiers uploadés d'une tâche dans
     * public/uploads/task_documents/{taskId}/ et crée les entités associées.
     *
     * @param UploadedFile[] $files
     */
    private function uploadTaskDocuments(ProjectTask $task, array $files, SluggerInterface $slugger, EntityManagerInterface $entityManager): void
    {
        $uploadDir = $this->projectDir . '/public/uploads/task_documents/' . $task->getId();
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName() ?? 'document';
            $safeName = strtolower($slugger->slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . uniqid() . '.' . ($file->guessExtension() ?? 'bin'));

            $file->move($uploadDir, $safeName);

            $document = new ProjectTaskDocument();
            $document->setTask($task)
                ->setFileName($safeName)
                ->setOriginalName($originalName)
                ->setMimeType($file->getMimeType() ?? 'application/octet-stream')
                ->setSize((int) $file->getSize())
                ->setUploadedBy($this->getUser());

            $entityManager->persist($document);
            $task->addDocument($document);
        }
    }

    /**
     * Supprime tous les fichiers d'une tâche sur le disque.
     */
    private function deleteTaskFiles(ProjectTask $task): void
    {
        foreach ($task->getDocuments() as $document) {
            $this->deleteTaskFile($document);
        }
    }

    private function deleteTaskFile(ProjectTaskDocument $document): void
    {
        $path = $this->projectDir . '/public/' . $document->getWebPath();
        if (is_file($path)) {
            unlink($path);
        }
    }
}
