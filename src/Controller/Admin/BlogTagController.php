<?php

namespace App\Controller\Admin;

use App\Entity\BlogTag;
use App\Form\BlogTagType;
use App\Repository\BlogTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/blog/categories', name: 'admin_blog_tag_')]
class BlogTagController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(BlogTagRepository $repo): Response
    {
        return $this->render('admin/blog_tag/index.html.twig', [
            'categories' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new BlogTag();
        $form = $this->createForm(BlogTagType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', 'Catégorie créée.');
            return $this->redirectToRoute('admin_blog_tag_index');
        }

        return $this->render('admin/blog_tag/form.html.twig', [
            'form' => $form, 'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BlogTag $category): Response
    {
        $form = $this->createForm(BlogTagType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Catégorie modifiée.');
            return $this->redirectToRoute('admin_blog_tag_index');
        }

        return $this->render('admin/blog_tag/form.html.twig', [
            'form' => $form, 'category' => $category,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, BlogTag $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', 'Catégorie supprimée.');
        }
        return $this->redirectToRoute('admin_blog_tag_index');
    }
}