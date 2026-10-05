<?php

namespace App\Controller\Admin;

use App\Entity\GalleryImage;
use App\Entity\PresentationPage;
use App\Enum\ImageVisibility;
use App\Form\PresentationPageType;
use App\Repository\PresentationPageRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/presentation', name: 'app_admin_presentation_')]
class PresentationPageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ImageManager $imageManager,
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function index(PresentationPageRepository $repo): Response
    {
        return $this->render('admin/presentation/index.html.twig', [
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
            $this->addFlash('success', 'Page supprimée avec succès.');
        }
        return $this->redirectToRoute('app_admin_presentation_index');
    }

    #[Route('/{id}/toggle-active', name: 'toggle_active', methods: ['POST'])]
    #[IsGranted('ENTITY_EDIT')]
    public function toggleActive(Request $request, PresentationPage $page): Response
    {
        if ($this->isCsrfTokenValid('toggle'.$page->getId(), $request->getPayload()->getString('_token'))) {
            $page->setIsActive(!$page->isActive());
            $page->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
            $this->addFlash('success', $page->isActive()
                ? 'Page rendue visible publiquement.'
                : 'Page masquée (brouillon).');
        }
        return $this->redirectToRoute('app_admin_presentation_index');
    }

    // =========================================
    // MÉTHODES PRIVÉES
    // =========================================

    private function handleForm(Request $request, PresentationPage $page, bool $isNew = false): Response
    {
        $form = $this->createForm(PresentationPageType::class, $page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Slug : généré à la création uniquement (les URLs restent stables ensuite)
            if ($isNew && ($page->getSlug() === null || $page->getSlug() === '')) {
                $page->computeSlug();
            }

            // Image téléversée : crée une GalleryImage « Publique », NON publiée (option A)
            $imageFile = $form->get('newImageFile')->getData();
            if ($imageFile instanceof UploadedFile) {
                $image = new GalleryImage();
                $image->setTitle($page->getTitle());
                $image->setAlt(trim($form->get('newImageAlt')->getData()));
                $image->setVisibility(ImageVisibility::Public);
                $image->giveConsent(); // daté automatiquement — déclenché par la case cochée (validée par le listener)
                $image->setConsentDetail(sprintf(
                    'Image ajoutée via la page de présentation « %s »',
                    $page->getTitle()
                ));
                $image->setUploadedBy($this->getUser());
                $image->setIsPublished(false); // OPTION A : publication par le président dans la galerie

                $this->imageManager->upload($image, $imageFile);
                $this->em->persist($image);
                $page->setGalleryImage($image);

                $this->addFlash('warning',
                    'Image ajoutée à la galerie en attente de publication. ' .
                    'Un président doit la publier dans « Gestion de la galerie » pour qu\'elle soit visible.'
                );
            }

            $page->setUpdatedAt(new \DateTimeImmutable());

            if ($isNew) {
                $this->em->persist($page);
            }
            $this->em->flush();

            $this->addFlash('success', $isNew ? 'Page créée avec succès.' : 'Page modifiée avec succès.');
            return $this->redirectToRoute('app_admin_presentation_index');
        }

        return $this->render('admin/presentation/form.html.twig', [
            'form' => $form,
            'page' => $page,
            'title' => $isNew ? 'Nouvelle page' : 'Modifier la page',
        ]);
    }
}