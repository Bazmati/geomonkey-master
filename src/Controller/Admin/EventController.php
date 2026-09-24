<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/event')]
final class EventController extends AbstractController
{
    #[Route(name: 'admin_events_index', methods: ['GET'])]
    public function index(EventRepository $eventRepository): Response
    {
        return $this->render('admin/event/index.html.twig', [
            'events' => $eventRepository->findBy([], ['startDate' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_events_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageManager->upload($event, $imageFile);
            }

            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'L\'événement a été créé avec succès.');

            return $this->redirectToRoute('admin_events_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/event/new.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_events_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Event $event,
        EntityManagerInterface $entityManager,
        ImageManager $imageManager
    ): Response {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image deletion
            $deleteImage = $form->get('deleteImage')->getData();
            if ($deleteImage && $event->hasImage()) {
                $imageManager->deleteForEntity($event);
                $event->setImage(null);
            }

            // Handle image upload (takes precedence over deletion)
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $imageManager->upload($event, $imageFile);
            }

            $entityManager->flush();

            $this->addFlash('success', 'L\'événement a été modifié avec succès.');

            return $this->redirectToRoute('admin_events_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_events_delete', methods: ['POST'])]
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
