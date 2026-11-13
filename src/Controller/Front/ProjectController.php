<?php

namespace App\Controller\Front;

use App\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projets', name: 'app_project_')]
class ProjectController extends AbstractController
{
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
}
