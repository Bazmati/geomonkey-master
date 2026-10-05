<?php

namespace App\Controller\Front;

use App\Entity\PresentationPage;
use App\Repository\PresentationPageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/presentation', name: 'app_presentation_')]
class PresentationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PresentationPageRepository $repo): Response
    {
        return $this->render('front/presentation/index.html.twig', [
            'pages' => $repo->findActiveOrdered(),
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(PresentationPage $page): Response
    {
        // Un brouillon n'est jamais visible publiquement, même en devinant l'URL
        if (!$page->isActive()) {
            throw $this->createNotFoundException();
        }

        return $this->render('front/presentation/show.html.twig', [
            'page' => $page,
        ]);
    }
}