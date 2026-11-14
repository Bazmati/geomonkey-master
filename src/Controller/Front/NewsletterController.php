<?php

namespace App\Controller\Front;

use App\Entity\NewsletterSubscriber;
use App\Repository\NewsletterSubscriberRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Newsletter : inscription depuis l'accueil + désinscription par lien tokenisé (RGPD).
 */
final class NewsletterController extends AbstractController
{
    public function __construct(
        private NewsletterSubscriberRepository $subscriberRepository,
        private EntityManagerInterface $em,
        private MailService $mailService,
    ) {}

    #[Route('/newsletter/inscription', name: 'app_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): Response
    {
        $back = $this->generateUrl('app_home', ['_fragment' => 'newsletter']);

        if (!$this->isCsrfTokenValid('newsletter_subscribe', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée : recharge la page puis réessaie.');

            return $this->redirect($back);
        }

        $email = trim((string) $request->request->get('email'));

        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse email invalide : vérifie ta saisie.');

            return $this->redirect($back);
        }

        $subscriber = $this->subscriberRepository->findOneBy(['email' => $email]);

        if (null !== $subscriber && $subscriber->isActive()) {
            $this->addFlash('info', 'Tu es déjà inscrit·e à la newsletter, merci !');

            return $this->redirect($back);
        }

        $isNew = null === $subscriber;
        if ($isNew) {
            $subscriber = new NewsletterSubscriber();
            $subscriber->setEmail($email);
        }

        // Nouveau jeton à chaque (ré)inscription : les anciens liens de désinscription deviennent invalides
        $subscriber
            ->setToken(bin2hex(random_bytes(32)))
            ->setIsActive(true)
            ->setSubscribedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))
            ->setUnsubscribedAt(null);

        if ($isNew) {
            $this->em->persist($subscriber);
        }
        $this->em->flush();

        // Email de bienvenue avec lien de désinscription (non bloquant)
        try {
            $this->mailService->sendNewsletterWelcome($subscriber);
        } catch (\Throwable) {
            // L'inscription reste valide même si le SMTP est indisponible
        }

        $this->addFlash('success', 'Inscription confirmée ! 🐒 Vérifie ta boîte mail pour le message de bienvenue.');

        return $this->redirect($back);
    }

    #[Route('/newsletter/desinscription/{token}', name: 'app_newsletter_unsubscribe', methods: ['GET'])]
    public function unsubscribe(string $token): Response
    {
        $subscriber = $this->subscriberRepository->findOneBy(['token' => $token]);

        if (null === $subscriber) {
            return $this->render('front/newsletter/unsubscribe.html.twig', [
                'status' => 'unknown',
            ]);
        }

        $alreadyOut = !$subscriber->isActive();

        if (!$alreadyOut) {
            $subscriber
                ->setIsActive(false)
                ->setUnsubscribedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));
            $this->em->flush();
        }

        return $this->render('front/newsletter/unsubscribe.html.twig', [
            'status' => $alreadyOut ? 'already' : 'ok',
            'email' => $subscriber->getEmail(),
        ]);
    }
}
