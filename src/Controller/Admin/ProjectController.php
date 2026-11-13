<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project')]
final class ProjectController extends AbstractController
{
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
}
