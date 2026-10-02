<?php

namespace App\Service;

use App\Entity\User;
use App\Service\MembershipFeeService;
use Stripe\Stripe;

class StripeService
{
    public function __construct(
        private string $stripeSecretKey,
        private string $successUrl,
        private string $cancelUrl,
        private string $webhookSecret,
        private MembershipFeeService $feeService
    ) {
        Stripe::setApiKey($this->stripeSecretKey);
    }

    /**
     * Crée une session de paiement Stripe pour l'adhésion
     */
    public function createMembershipCheckoutSession(User $user): string
    {
        $amountCents = $this->feeService->getCurrentAmount(); // Lance exception si vide

        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $amountCents,
                    'product_data' => [
                        'name' => 'Adhésion GéoMonkey',
                        'description' => sprintf(
                            'Cotisation annuelle: %.2f€ (valable jusqu\'au %s)',
                            $amountCents / 100,
                            (new \DateTimeImmutable())->add(new \DateInterval('P1Y'))->format('d/m/Y')
                        ),
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $this->successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->cancelUrl,
            'metadata' => [
                'user_id' => (string) $user->getId(),
                'membership_type' => 'annual',
            ],
            'client_reference_id' => (string) $user->getId(),
        ]);

        return $session->url;
    }

    /**
     * Vérifie la signature du webhook Stripe
     */
    public function verifyWebhookSignature(string $payload, string $sigHeader): \Stripe\Event
    {
        return \Stripe\Webhook::constructEvent($payload, $sigHeader, $this->webhookSecret);
    }

    /**
     * Getter pour le webhook secret (pour les tests)
     */
    public function getWebhookSecret(): string
    {
        return $this->webhookSecret;
    }
}
