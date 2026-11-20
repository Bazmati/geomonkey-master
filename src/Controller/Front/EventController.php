<?php

namespace App\Controller\Front;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Enum\ParticipantRole;
use App\Entity\User;
use App\Repository\EventCategoryRepository;
use App\Repository\EventParticipantRepository;
use App\Repository\EventTagRepository;
use App\Repository\EventRepository;
use App\Repository\GalleryImageRepository;
use App\Repository\MembershipRepository;
use App\Service\EventIcsGenerator;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/evenements', name: 'app_event_')]
class EventController extends AbstractController
{
    private const FILTERS = ['tous', 'a-venir', 'passes'];

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly ImageManager $imageManager,
        private readonly EventParticipantRepository $participantRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventIcsGenerator $icsGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        GalleryImageRepository $galleryRepo,
        EventCategoryRepository $categoryRepo,
        EventTagRepository $tagRepo
    ): Response
    {
        $filter = $request->query->get('filter', 'tous');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'tous';
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = null !== $user && $this->membershipRepository->hasActiveMembership($user);

        $events = $this->entityManager->getRepository(Event::class)
            ->findVisibleFor($isActiveMember, $filter);

        // Filtres par catégorie et par tag (clique dans la sidebar)
        $currentCategory = null;
        $currentTag = null;
        $categorySlug = trim((string) $request->query->get('category', ''));
        $tagSlug = trim((string) $request->query->get('tag', ''));

        if ($categorySlug !== '') {
            $currentCategory = $categoryRepo->findOneBy(['slug' => $categorySlug]);
            if ($currentCategory === null) {
                throw $this->createNotFoundException($this->translator->trans('event.error.category_unknown'));
            }
            $events = array_values(array_filter(
                $events,
                static fn (Event $e) => $e->getCategory() !== null && $e->getCategory()->getId() === $currentCategory->getId()
            ));
        }

        if ($tagSlug !== '') {
            $currentTag = $tagRepo->findOneBy(['slug' => $tagSlug]);
            if ($currentTag === null) {
                throw $this->createNotFoundException($this->translator->trans('event.error.tag_unknown'));
            }
            $events = array_values(array_filter(
                $events,
                static fn (Event $e) => $e->getTags()->contains($currentTag)
            ));
        }

        return $this->render('front/event/index.html.twig', [
            'events' => $events,
            'filter' => $filter,
            'event_categories' => $categoryRepo->findUsedCategories(),
            'event_tags' => $tagRepo->findUsedTags(),
            'currentCategory' => $currentCategory,
            'currentTag' => $currentTag,
            // Si aucun événement n'est programmé, l'affiche laisse place
            // à un défilement d'images de la galerie (visibles par l'utilisateur).
            'gallery_images' => array_slice($galleryRepo->findVisibleForUser($user), 0, 12),
        ]);
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function data(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = null !== $user && $this->membershipRepository->hasActiveMembership($user);

        $events = $this->entityManager->getRepository(Event::class)
            ->findVisibleFor($isActiveMember);

        $data = [];
        foreach ($events as $event) {
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start' => $event->getStartDate()?->format(DATE_ATOM),
                'end' => $event->getEndDate()?->format(DATE_ATOM),
                'url' => $this->generateUrl('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]),
                'membersOnly' => $event->isMembersOnly(),
                'location' => $event->getLocation(),
                'past' => $event->getStartDate() !== null && $event->getStartDate()->getTimestamp() < time(),
            ];
        }

        return $this->json($data);
    }

    #[Route('/{slug}', name: 'show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = $this->isViewerActiveMember();
        $repo = $this->entityManager->getRepository(Event::class);

        // Ancien format /evenements/17 : redirection permanente vers le slug
        if (ctype_digit($slug)) {
            $byId = $repo->findOneVisibleBy((int) $slug, $isActiveMember);
            if ($byId === null) {
                throw $this->createNotFoundException($this->translator->trans('event.error.not_found'));
            }

            return $this->redirectToRoute(
                'app_event_show',
                ['slug' => $byId->getSlug() ?: (string) $byId->getId()],
                301
            );
        }

        $event = $repo->findOneVisibleBySlug($slug, $isActiveMember);
        if ($event === null) {
            throw $this->createNotFoundException($this->translator->trans('event.error.not_found'));
        }

        $participantCount = $this->participantRepository->countParticipants($event);
        $capacity = $event->getCapacity();

        return $this->render('front/event/show.html.twig', [
            'event' => $event,
            'is_active_member' => $isActiveMember,
            'isRegistered' => null !== $user && $this->participantRepository->userParticipatesInEvent($user, $event),
            'participantCount' => $participantCount,
            'isPast' => $event->getStartDate() !== null && $event->getStartDate()->getTimestamp() < time(),
            'isFull' => null !== $capacity && $participantCount >= $capacity,
        ]);
    }

    #[Route('/{id}/register', name: 'register', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function register(Request $request, int $id): Response
    {
        $event = $this->findVisibleEventOr404($id);

        /** @var User|null $user */
        $user = $this->getUser();
        if (null === $user) {
            $this->addFlash('warning', $this->translator->trans('flash.event.login_required'));

            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('register' . $event->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('flash.event.csrf'));

            return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
        }

        // Événement réservé aux membres : adhésion active obligatoire
        if ($event->isMembersOnly() && !$this->isViewerActiveMember()) {
            $this->addFlash('warning', $this->translator->trans('flash.event.members_only'));

            return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
        }

        // Événement déjà commencé
        if ($event->getStartDate() !== null && $event->getStartDate()->getTimestamp() < time()) {
            $this->addFlash('warning', $this->translator->trans('flash.event.started'));

            return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
        }

        // Capacité atteinte
        if (null !== $event->getCapacity()
            && $this->participantRepository->countParticipants($event) >= $event->getCapacity()) {
            $this->addFlash('warning', $this->translator->trans('flash.event.full'));

            return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
        }

        // Déjà inscrit
        if ($this->participantRepository->userParticipatesInEvent($user, $event)) {
            $this->addFlash('info', $this->translator->trans('flash.event.already_registered'));

            return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
        }

        $participant = new EventParticipant();
        $participant->setEvent($event)
            ->setUser($user)
            ->setRole(ParticipantRole::Participant);

        $this->entityManager->persist($participant);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.event.registered'));

        return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
    }

    #[Route('/{id}/unregister', name: 'unregister', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unregister(Request $request, int $id): Response
    {
        $event = $this->findVisibleEventOr404($id);

        /** @var User|null $user */
        $user = $this->getUser();
        if (null === $user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('unregister' . $event->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', $this->translator->trans('flash.event.csrf'));

            return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
        }

        $participant = $this->participantRepository->findOneBy(['event' => $event, 'user' => $user]);
        if (null === $participant) {
            $this->addFlash('info', $this->translator->trans('flash.event.not_registered'));

            return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
        }

        $this->entityManager->remove($participant);
        $this->entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.event.unregistered'));

        return $this->redirectToRoute('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()]);
    }

    #[Route('/{id}.ics', name: 'ics', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function ics(int $id): Response
    {
        $event = $this->findVisibleEventOr404($id);

        $url = $this->generateUrl('app_event_show', ['slug' => $event->getSlug() ?: (string) $event->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->icsGenerator->response($event, $url);
    }

    /**
     * Retrouve un événement publié visible pour l'utilisateur courant, sinon 404.
     */
    private function findVisibleEventOr404(int $id): Event
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $isActiveMember = null !== $user && $this->membershipRepository->hasActiveMembership($user);

        $event = $this->entityManager->getRepository(Event::class)
            ->findOneVisibleBy($id, $isActiveMember);

        if (null === $event) {
            throw $this->createNotFoundException($this->translator->trans('event.error.not_found'));
        }

        return $event;
    }

    private function isViewerActiveMember(): bool
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return null !== $user && $this->membershipRepository->hasActiveMembership($user);
    }
}
