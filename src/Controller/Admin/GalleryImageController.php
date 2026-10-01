<?php

namespace App\Controller\Admin;

use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Form\GalleryImageType;
use App\Repository\GalleryImageRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/gallery-image', name: 'app_admin_gallery_image_')]
class GalleryImageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ImageManager $imageManager,
        private Security $security,
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function index(GalleryImageRepository $galleryImageRepository): Response
    {
        $images = $galleryImageRepository->findAll();

        return $this->render('admin/gallery_image/index.html.twig', [
            'gallery_images' => $images,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('GALLERY_IMAGE_CREATE')]
    public function new(Request $request): Response
    {
        $galleryImage = new GalleryImage();

        // Définir l'utilisateur qui upload
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $galleryImage->setUploadedBy($user);
        }

        return $this->handleForm($request, $galleryImage, true);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('GALLERY_IMAGE_EDIT')]
    public function edit(Request $request, GalleryImage $galleryImage): Response
    {
        return $this->handleForm($request, $galleryImage);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('GALLERY_IMAGE_DELETE')]
    public function delete(Request $request, GalleryImage $galleryImage): Response
    {
        // Vérifier que l'image n'est pas utilisée
        if ($galleryImage->isUsed()) {
            $usageCount = $galleryImage->getUsageCount();
            $this->addFlash('error', sprintf(
                'Impossible de supprimer cette image : elle est utilisée dans %d événement(s)/article(s).',
                $usageCount
            ));
            return $this->redirectToRoute('app_admin_gallery_image_index');
        }

        if ($this->isCsrfTokenValid('delete' . $galleryImage->getId(), $request->getPayload()->getString('_token'))) {
            // Supprimer l'image du système de fichiers
            if ($galleryImage->hasImage()) {
                $this->imageManager->deleteForEntity($galleryImage);
            }

            $this->em->remove($galleryImage);
            $this->em->flush();

            $this->addFlash('success', 'Image de galerie supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_gallery_image_index');
    }

    #[Route('/{id}/publish', name: 'publish', methods: ['POST'])]
    #[IsGranted('GALLERY_IMAGE_PUBLISH', 'galleryImage')]
    public function publish(Request $request, GalleryImage $galleryImage): Response
    {
        if ($this->isCsrfTokenValid('publish' . $galleryImage->getId(), $request->getPayload()->getString('_token'))) {
            // Double vérification RGPD
            if ($galleryImage->getVisibility() === ImageVisibility::Public && !$galleryImage->hasValidConsent()) {
                $this->addFlash('error', 'Publication impossible : une image publique exige un consentement RGPD daté.');
                return $this->redirectToRoute('app_admin_gallery_image_index');
            }

            // Toggle publish state
            $galleryImage->setIsPublished(!$galleryImage->isPublished());
            $galleryImage->setUpdatedAt(new \DateTime());
            $this->em->flush();

            $action = $galleryImage->isPublished() ? 'publiée' : 'dépubliée';
            $this->addFlash('success', sprintf('Image %s avec succès.', $action));
        }

        return $this->redirectToRoute('app_admin_gallery_image_index');
    }

    #[Route('/{id}/withdraw-consent', name: 'withdraw_consent', methods: ['POST'])]
    #[IsGranted('GALLERY_IMAGE_EDIT')]
    public function withdrawConsent(Request $request, GalleryImage $galleryImage): Response
    {
        if ($this->isCsrfTokenValid('withdraw_consent' . $galleryImage->getId(), $request->getPayload()->getString('_token'))) {
            $wasPublished = $galleryImage->isPublished();

            // Utiliser la méthode dédiée qui gère la rétrogradation auto
            $galleryImage->withdrawConsent();

            // Si l'image était publiée, on la dépublie aussi
            if ($wasPublished) {
                $galleryImage->setIsPublished(false);
                $this->addFlash('warning', 'Le consentement a été retiré. L\'image a été rétrogradée en "Membres" et dépubliée pour conformité RGPD.');
            } else {
                $this->addFlash('warning', 'Le consentement a été retiré. L\'image a été rétrogradée en "Membres" pour conformité RGPD.');
            }

            $galleryImage->setUpdatedAt(new \DateTime());
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_gallery_image_index');
    }

    // =========================================
    // MÉTHODES PRIVÉES (Optimisation)
    // =========================================

    /**
     * Gère le formulaire de création/édition d'une image.
     */
    private function handleForm(
        Request $request,
        GalleryImage $galleryImage,
        bool $isNew = false
    ): Response {
        // Filtrer les choix de visibilité en fonction du consentement RGPD
        $visibilityChoices = $this->filterVisibilityChoices($galleryImage);

        // Créer le formulaire avec les options dynamiques
        $form = $this->createForm(GalleryImageType::class, $galleryImage, [
            'visibility_choices' => $visibilityChoices,
            'require_image' => $isNew,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->processFormSubmission($form, $galleryImage, $isNew);
        }

        // Affiche le formulaire
        return $this->render('admin/gallery_image/form.html.twig', [
            'form' => $form,
            'gallery_image' => $galleryImage,
            'title' => $isNew ? 'Nouvelle image' : 'Modifier l\'image',
        ]);
    }

    /**
     * Traite la soumission du formulaire.
     */
    private function processFormSubmission(
        \Symfony\Component\Form\FormInterface $form,
        GalleryImage $galleryImage,
        bool $isNew
    ): Response {
        // Gestion du consentement RGPD
        $this->handleRgpdConsent($galleryImage);

        // Gestion de l'upload d'image
        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile instanceof UploadedFile) {
            if ($galleryImage->hasImage()) {
                $this->imageManager->deleteForEntity($galleryImage);
            }
            $this->imageManager->upload($galleryImage, $imageFile);
        }

        // Valider que les images publiques ont le consentement RGPD
        if ($galleryImage->getVisibility() === ImageVisibility::Public && !$galleryImage->hasValidConsent()) {
            $this->addFlash('error', 'Les images publiques nécessitent un consentement RGPD valide (coché ET daté).');
            return $this->redirectToRoute('app_admin_gallery_image_index');
        }

        // Persister si c'est une nouvelle image
        if ($isNew) {
            $this->em->persist($galleryImage);
        } else {
            $galleryImage->setUpdatedAt(new \DateTime());
        }

        $this->em->flush();
        $this->addFlash('success', $isNew ? 'Image de galerie créée avec succès.' : 'Image de galerie modifiée avec succès.');

        return $this->redirectToRoute('app_admin_gallery_image_index');
    }

    /**
     * Gère le consentement RGPD.
     */
    private function handleRgpdConsent(GalleryImage $galleryImage): void
    {
        if ($galleryImage->hasRgpdConsent() && !$galleryImage->getConsentedAt()) {
            $galleryImage->giveConsent();
        }
        if (!$galleryImage->hasRgpdConsent() && $galleryImage->getConsentedAt()) {
            $galleryImage->withdrawConsent();
        }
    }

    /**
     * Filtre les choix de visibilité en fonction du consentement RGPD.
     */
    private function filterVisibilityChoices(GalleryImage $galleryImage): array
    {
        $visibilityChoices = ImageVisibility::all();
        if (!$galleryImage->hasRgpdConsent()) {
            $visibilityChoices = array_filter($visibilityChoices, fn($v) => $v !== ImageVisibility::Public);
        }
        return $visibilityChoices;
    }
}
