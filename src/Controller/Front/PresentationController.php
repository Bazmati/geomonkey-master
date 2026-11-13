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
        #[MapEntity(mapping: ['slug' => 'slug'])] PresentationPage $page,
        PresentationPageRepository $repo,
    ): Response {
        if (!$page->isActive()) {
            throw $this->createNotFoundException();
        }

        // Pages voisines (navigation précédent / suivant)
        $all = $repo->findActiveOrdered();
        $position = null;
        foreach ($all as $i => $activePage) {
            if ($activePage->getId() === $page->getId()) {
                $position = $i;
                break;
            }
        }
        $prev = null !== $position && $position > 0 ? $all[$position - 1] : null;
        $next = null !== $position && $position < \count($all) - 1 ? $all[$position + 1] : null;

        return $this->render('front/presentation/show.html.twig', [
            'page' => $page,
            'prev' => $prev,
            'next' => $next,
            'image_url' => $page->getGalleryImage()?->isPublished()
                ? $this->imageManager->getUrl($page->getGalleryImage(), 'large')
                : null,
        ]);
    }
}
