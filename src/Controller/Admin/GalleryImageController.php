<?php

namespace App\Controller\Admin;

use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Enum\OfficeFunction;
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

        $form = $this->createForm(GalleryImageType::class, $galleryImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload d'image
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile instanceof UploadedFile) {
                $this->imageManager->upload($galleryImage, $imageFile);
            }

            // Valider que les images publiques ont le consentement RGPD
            if ($galleryImage->getVisibility() === ImageVisibility::Public && !$galleryImage->hasRgpdConsent()) {
                $this->addFlash('error', 'Les images publiques nécessitent un consentement RGPD.');
                return $this->render('admin/gallery_image/form.html.twig', [
                    'form' => $form,
                    'gallery_image' => $galleryImage,
                    'title' => 'Nouvelle image',
                ]);
            }

            $this->em->persist($galleryImage);
            $this->em->flush();

            $this->addFlash('success', 'Image de galerie créée avec succès.');
            return $this->redirectToRoute('app_admin_gallery_image_index');
        }

        return $this->render('admin/gallery_image/form.html.twig', [
            'form' => $form,
            'gallery_image' => $galleryImage,
            'title' => 'Nouvelle image',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('GALLERY_IMAGE_EDIT')]
    public function edit(Request $request, GalleryImage $galleryImage): Response
    {
        $form = $this->createForm(GalleryImageType::class, $galleryImage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload d'image
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile instanceof UploadedFile) {
                // Supprimer l'ancienne image
                if ($galleryImage->hasImage()) {
                    $this->imageManager->deleteForEntity($galleryImage);
                }
                $this->imageManager->upload($galleryImage, $imageFile);
            }

            // Valider que les images publiques ont le consentement RGPD
            if ($galleryImage->getVisibility() === ImageVisibility::Public && !$galleryImage->hasRgpdConsent()) {
                $this->addFlash('error', 'Les images publiques nécessitent un consentement RGPD.');
                return $this->render('admin/gallery_image/form.html.twig', [
                    'form' => $form,
                    'gallery_image' => $galleryImage,
                    'title' => 'Modifier l\'image',
                ]);
            }

            $galleryImage->setUpdatedAt(new \DateTime());
            $this->em->flush();

            $this->addFlash('success', 'Image de galerie modifiée avec succès.');
            return $this->redirectToRoute('app_admin_gallery_image_index');
        }

        return $this->render('admin/gallery_image/form.html.twig', [
            'form' => $form,
            'gallery_image' => $galleryImage,
            'title' => 'Modifier l\'image',
        ]);
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
    #[IsGranted('GALLERY_IMAGE_PUBLISH')]
    public function publish(Request $request, GalleryImage $galleryImage): Response
    {
        if ($this->isCsrfTokenValid('publish' . $galleryImage->getId(), $request->getPayload()->getString('_token'))) {
            // Vérifier le consentement RGPD pour les images publiques
            if ($galleryImage->getVisibility() === ImageVisibility::Public && !$galleryImage->hasRgpdConsent()) {
                $this->addFlash('error', 'Les images publiques nécessitent un consentement RGPD pour être publiées.');
                return $this->redirectToRoute('app_admin_gallery_image_index');
            }

            // Seuls le président peut publier une image Public
            $user = $this->security->getUser();
            if ($galleryImage->getVisibility() === ImageVisibility::Public && 
                $user instanceof User && 
                $user->getOfficeFunction() !== OfficeFunction::President) {
                $this->addFlash('error', 'Seul le président peut publier une image publique.');
                return $this->redirectToRoute('app_admin_gallery_image_index');
            }

            $galleryImage->setIsPublished(!$galleryImage->isPublished());
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
            // Retirer le consentement
            $galleryImage->setRgpdConsent(false);
            $galleryImage->setConsentedAt(null);
            $galleryImage->setConsentDetail(null);

            // Si l'image est publique, la rétrograder à Members (conformité RGPD)
            if ($galleryImage->getVisibility() === ImageVisibility::Public) {
                $galleryImage->setVisibility(ImageVisibility::Members);
                // Si l'image était publiée, la dépublier
                if ($galleryImage->isPublished()) {
                    $galleryImage->setIsPublished(false);
                    $this->addFlash('warning', 'Le consentement a été retiré. L\'image a été rétrogradée en "Membres" et dépubliée pour conformité RGPD.');
                } else {
                    $this->addFlash('warning', 'Le consentement a été retiré. L\'image a été rétrogradée en "Membres" pour conformité RGPD.');
                }
            } else {
                $this->addFlash('success', 'Le consentement a été retiré.');
            }

            $galleryImage->setUpdatedAt(new \DateTime());
            $this->em->flush();
        }

        return $this->redirectToRoute('app_admin_gallery_image_index');
    }
}