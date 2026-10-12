<?php

namespace App\Controller\Front;

use App\Entity\BlogPost;
use App\Repository\BlogPostRepository;
use App\Service\ImageManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/blog', name: 'app_blog_')]
class BlogController extends AbstractController
{
    public function __construct(
        private ImageManager $imageManager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(BlogPostRepository $repo): Response
    {
        return $this->render('front/blog/index.html.twig', [
            'posts' => $repo->findPublishedPosts(),
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] BlogPost $post
    ): Response {
        // Double barrière : MapEntity garantit l'existence, ce contrôle
        // garantit la publication. Un brouillon = 404, jamais servi.
        if (!$post->isPublished()) {
            throw $this->createNotFoundException();
        }

        return $this->render('front/blog/show.html.twig', [
            'post' => $post,
        ]);
    }
}