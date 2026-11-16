<?php

namespace App\Controller\Admin;

use App\Entity\EventTag;
use App\Form\EventTagType;
use App\Repository\EventTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/evenements/tags', name: 'admin_event_tag_')]
class EventTagController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EventTagRepository $repo): Response
    {
        return $this->render('admin/event_tag/index.html.twig', [
            'tags' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $category = new EventTag();
        $form = $this->createForm(EventTagType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($category);
            $this->em->flush();
            $this->addFlash('success', 'Tag créé.');
            return $this->redirectToRoute('admin_event_tag_index');
        }

        return $this->render('admin/event_tag/form.html.twig', [
            'form' => $form, 'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EventTag $category): Response
    {
        $form = $this->createForm(EventTagType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Tag modifié.');
            return $this->redirectToRoute('admin_event_tag_index');
        }

        return $this->render('admin/event_tag/form.html.twig', [
            'form' => $form, 'category' => $category,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, EventTag $category): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            $this->em->remove($category);
            $this->em->flush();
            $this->addFlash('success', 'Tag supprimé.');
        }
        return $this->redirectToRoute('admin_event_tag_index');
    }
}
