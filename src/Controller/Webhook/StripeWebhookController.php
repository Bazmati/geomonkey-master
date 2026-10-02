<?php

namespace App\Controller\Webhook;

use App\Entity\Membership;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Service\MembershipFeeService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour gérer les webhooks Stripe
 * 
 * Ce contrôleur écoute les événements Stripe, notamment :
 * - checkout.session.completed : Paiement réussi → M4 (Félicitations)
 * - payment_intent.succeeded : Alternative pour confirmer le paiement
 * 
 * POUR TESTER EN LOCAL :
 * 1. Lancer ngrok : ngrok http 8000
 * 2. Dans Stripe Dashboard → Developers → Webhooks → Add endpoint
 * 3. URL : https://xxxx.ngrok.io/webhook/stripe
 * 4. Sélectionner l'événement : checkout.session.completed
 * 5. Récupérer le webhook secret et le mettre dans .env
 */
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private MailService $mailService,
        private MembershipFeeService $feeService,
        private StripeService $stripeService
    ) {}

    #[Route('/webhook/stripe', name: 'app_webhook_stripe', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        // Récupérer le payload et la signature
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        if (!$payload || !$sigHeader) {
            return new Response('Webhook Error: Missing payload or signature', 400);
        }

        try {
            // Vérifier la signature du webhook
            $event = $this->stripeService->verifyWebhookSignature($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            return new Response('Webhook Error: Invalid signature', 400);
        }

        // Gérer l'événement
        switch ($event->type) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($event);

            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($event);

            default:
                // Événement non géré - retourner 200 pour éviter les retries
                return new Response('Event type not handled', 200);
        }
    }

    /**
     * Traite l'événement checkout.session.completed
     * Declenche l'envoi du mail M4 : Félicitations + invitation WhatsApp + assurance
     */
    private function handleCheckoutSessionCompleted(Event $event): Response
    {
        $session = $event->data->object;

        // Récupérer l'utilisateur via le metadata
        if (!isset($session->client_reference_id)) {
            return new Response('No user reference in session', 200);
        }

        $userId = (int) $session->client_reference_id;
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return new Response(sprintf('User %d not found', $userId), 200);
        }

        // Récupérer ou créer une membership pour cet utilisateur
        $membership = $this->createOrUpdateMembership($user, $session);

        // M4: Envoyer le mail de confirmation
        $this->mailService->sendMembershipConfirmedEmail($user);

        // Marquer que l'utilisateur peut accéder aux contenus membres
        $user->setIsRegistrationConfirmed(true);
        $user->setRegistrationValidatedAt(new \DateTimeImmutable());
        
        // Stocker le payment ID
        $membership->setStripePaymentId($session->id);
        
        $this->em->persist($user);
        $this->em->persist($membership);
        $this->em->flush();

        return new Response('Checkout session completed handled', 200);
    }

    /**
     * Traite l'événement payment_intent.succeeded (alternative)
     */
    private function handlePaymentIntentSucceeded(Event $event): Response
    {
        $paymentIntent = $event->data->object;

        // Chercher l'utilisateur via le metadata
        if (!isset($paymentIntent->metadata->user_id)) {
            return new Response('No user reference in payment intent', 200);
        }

        $userId = (int) $paymentIntent->metadata->user_id;
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return new Response(sprintf('User %d not found', $userId), 200);
        }

        // M4: Envoyer le mail de confirmation
        $this->mailService->sendMembershipConfirmedEmail($user);

        return new Response('Payment intent succeeded handled', 200);
    }

    /**
     * Crée ou met à jour une membership pour l'utilisateur
     */
    private function createOrUpdateMembership(User $user, object $session): Membership
    {
        try {
            $amountCents = $this->feeService->getCurrentAmount();
        } catch (\RuntimeException $e) {
            $amountCents = 1500; // 15.00 € par défaut
        }

        // Chercher une membership non expirée pour cet utilisateur
        foreach ($user->getMemberships() as $membership) {
            if (!$membership->isExpired()) {
                // Mettre à jour la membership existante
                $membership->setIsActive(true);
                $membership->setAmount($amountCents);
                $membership->setRenewedAt(new \DateTimeImmutable());
                $membership->setStripePaymentId($session->id);
                return $membership;
            }
        }

        // Créer une nouvelle membership
        $membership = new Membership();
        $membership->setUser($user);
        $membership->setAmount($amountCents);
        $membership->setIsActive(true);
        $membership->setStripePaymentId($session->id);
        
        return $membership;
    }
}
