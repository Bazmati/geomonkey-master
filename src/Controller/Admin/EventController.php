<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Entity\GalleryImage;
use App\Enum\ImageVisibility;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/event')]
final class EventController extends AbstractController
{
    #[Route(name: 'admin_events_index', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('admin/event/index.html.twig', [
            'events' => $eventRepository->findBy([], ['startDate' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_events_new', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_CREATE')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $entityManager->persist($event);
            $entityManager->flush();

            $imageFile = $form->get('newImageFile')->getData();

            if ($imageFile) {
                $image = new GalleryImage();
                $image->setTitle($event->getTitle());
                $image->setAlt(trim($form->get('newImageAlt')->getData()));
                $image->setVisibility(ImageVisibility::Public);
                $image->giveConsent();
                $image->setConsentDetail(sprintf('Image ajoutée via l\'événement « %s »', $event->getTitle()));
                $image->setUploadedBy($this->getUser());
                $image->setIsPublished(false);
                $image->setImage('pending-' . bin2hex(random_bytes(8)));

                $entityManager->persist($image);
                $entityManager->flush();              // ← ID généré, upload dans gallery/{id}/
                $imageManager->upload($image, $imageFile);

                $event->addGalleryImage($image);      // ← liaison ManyToMany déjà existante
                $entityManager->flush();             // persiste l'UUID réel

                $this->addFlash('warning',
                    'Image ajoutée à la galerie en attente de publication. '.
                    'Un président doit la publier dans « Gestion de la galerie » pour qu\'elle soit visible.'
                );
            }

            $this->addFlash('success', 'L\'événement a été créé avec succès.');

            return $this->redirectToRoute('admin_events_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_events_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_EDIT', 'event')]
    public function edit(
        Request $request,
        Event $event,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Handle image upload (takes precedence over deletion)
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageManager->upload($event, $imageFile);
            }

            $entityManager->flush();

            $imageFile = $form->get('newImageFile')->getData();

            if ($imageFile) {
                $image = new GalleryImage();
                $image->setTitle($event->getTitle());
                $image->setAlt(trim($form->get('newImageAlt')->getData()));
                $image->setVisibility(ImageVisibility::Public);
                $image->giveConsent();
                $image->setConsentDetail(sprintf('Image ajoutée via l\'événement « %s »', $event->getTitle()));
                $image->setUploadedBy($this->getUser());
                $image->setIsPublished(false);
                $image->setImage('pending-' . bin2hex(random_bytes(8)));

                $entityManager->persist($image);
                $entityManager->flush();              // ← ID généré, upload dans gallery/{id}/
                $imageManager->upload($image, $imageFile);

                $event->addGalleryImage($image);      // ← liaison ManyToMany déjà existante
                $entityManager->flush();             // persiste l'UUID réel

                $this->addFlash('warning',
                    'Image ajoutée à la galerie en attente de publication. '.
                    'Un président doit la publier dans « Gestion de la galerie » pour qu\'elle soit visible.'
                );
            }

            $this->addFlash('success', 'L\'événement a été modifié avec succès.');

            return $this->redirectToRoute('admin_events_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_events_delete', methods: ['POST'])]
    #[IsGranted('ENTITY_DELETE', 'event')]
    public function delete(
        Request $request,
        Event $event,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        if ($this->isCsrfTokenValid('delete'.$event->getId(), $request->getPayload()->getString('_token'))) {
            // Delete images before removing entity
            $imageManager->deleteForEntity($event);
            
            $entityManager->remove($event);
            $entityManager->flush();

            $this->addFlash('success', 'L\'événement a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_events_index', [], Response::HTTP_SEE_OTHER);
    }
}
