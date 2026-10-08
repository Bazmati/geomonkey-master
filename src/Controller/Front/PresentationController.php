<?php

namespace App\Controller\Front;

use App\Entity\PresentationPage;
use App\Repository\PresentationPageRepository;
use App\Service\ImageManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/presentation', name: 'app_presentation_')]
class PresentationController extends AbstractController
{
    public function __construct(
        private ImageManager $imageManager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PresentationPageRepository $repo): Response
    {
        return $this->render('front/presentation/index.html.twig', [
            'pages' => $repo->findActiveOrdered(),
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] PresentationPage $page
    ): Response {
        if (!$page->isActive()) {
            throw $this->createNotFoundException();
        }

        return $this->render('front/presentation/show.html.twig', [
            'page' => $page,
            'image_url' => $page->getGalleryImage()?->isPublished()
                ? $this->imageManager->getUrl($page->getGalleryImage(), 'large')
                : null,
        ]);
    }
}