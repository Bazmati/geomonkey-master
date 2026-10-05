<?php

namespace App\Front\Controller;

use App\Entity\User;
use App\Repository\GalleryImageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour l'affichage public de la galerie.
 * 
 * Note : Pas de #[IsGranted] ici - la galerie est accessible à tous.
 * Le filtrage se fait au niveau du repository (findVisibleForUser) qui ne
 * renvoie que ce que l'utilisateur a le droit de voir.
 */
class GalleryController extends AbstractController
{
    #[Route('/galerie', name: 'app_gallery_index')]
    public function index(GalleryImageRepository $repo): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $this->render('front/gallery/index.html.twig', [
            'images' => $repo->findVisibleForUser($user),
        ]);
    }
}
