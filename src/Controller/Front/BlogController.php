<?php

namespace App\Controller\Front;

use App\Entity\BlogPost;
use App\Repository\BlogPostRepository;
use App\Service\ImageManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\BlogCategoryRepository;
use App\Repository\BlogTagRepository;

#[Route('/blog', name: 'app_blog_')]
class BlogController extends AbstractController
{
    public function __construct(
        private ImageManager $imageManager,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(BlogPostRepository $repo, BlogTagRepository $tagRepo): Response
    {
        return $this->render('front/blog/index.html.twig', [
            'posts' => $repo->findPublishedPosts(),
            'tags' => $tagRepo->findUsedTags(),
        ]);
    }

        #[Route('/categorie/{slug}', name: 'category', methods: ['GET'])]
    public function category(string $slug, BlogCategoryRepository $repo): Response
    {
        $category = $repo->findOneBy(['slug' => $slug]);
        if (!$category) {
            throw $this->createNotFoundException('Catégorie inconnue.');
        }

        return $this->render('front/blog/category.html.twig', [
            'category' => $category,
            'posts' => $category->getPosts()->filter(static fn ($p) => $p->isPublished()),
        ]);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] BlogPost $post
    ): Response {
        if (!$post->isPublished()) {
            throw $this->createNotFoundException();
        }

        return $this->render('front/blog/show.html.twig', [
            'post' => $post,
            'image_url' => $this->imageManager->getUrl($post, 'large'),
        ]);
    }
}