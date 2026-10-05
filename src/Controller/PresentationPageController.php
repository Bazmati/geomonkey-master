<?php

namespace App\Controller;

use App\Entity\PresentationPage;
use App\Form\PresentationPageType;
use App\Repository\PresentationPageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/presentation', name: 'app_admin_presentation_')]
class PresentationPageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function index(PresentationPageRepository $repo): Response
    {
        return $this->render('presentation_page/index.html.twig', [
            'pages' => $repo->findBy([], ['position' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_CREATE')]
    public function new(Request $request): Response
    {
        return $this->handleForm($request, new PresentationPage(), true);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_EDIT')]
    public function edit(Request $request, PresentationPage $page): Response
    {
        return $this->handleForm($request, $page);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ENTITY_DELETE')]
    public function delete(Request $request, PresentationPage $page): Response
    {
        if ($this->isCsrfTokenValid('delete'.$page->getId(), $request->getPayload()->getString('_token'))) {
            $this->em->remove($page);
            $this->em->flush();
            $this->addFlash('success', 'Page supprimée.');
        }
        return $this->redirectToRoute('app_admin_presentation_index');
    }

    // =========================================
    // MÉTHODE PRIVÉE
    // =========================================

    /**
     * Affiche et traite le formulaire de création/édition.
     */
    private function handleForm(Request $request, PresentationPage $page, bool $isNew = false): Response
    {
        $form = $this->createForm(PresentationPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isNew) {
                $page->computeSlug();
                $this->em->persist($page);
            }
            $this->em->flush();

            $this->addFlash('success', $isNew ? 'Page créée avec succès.' : 'Page modifiée avec succès.');
            return $this->redirectToRoute('app_admin_presentation_index');
        }

        return $this->render('presentation_page/form.html.twig', [
            'form' => $form,
            'page' => $page,
            'title' => $isNew ? 'Nouvelle page' : 'Modifier la page',
        ]);
    }
}