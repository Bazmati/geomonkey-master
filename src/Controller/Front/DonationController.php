<?php

namespace App\Controller\Front;

use App\Entity\Donation;
use App\Form\DonationType;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page publique de don libre
 *
 * Flux : formulaire (montant en euros) -> entité Donation (centimes, pending)
 * -> session Checkout Stripe (metadata type=donation) -> redirection Stripe
 * -> webhook checkout.session.completed -> passage en "paid" + M8/M9
 */
#[Route('/don')]
class DonationController extends AbstractController
{
    public function __construct(
        private StripeService $stripeService,
    ) {}

    #[Route('', name: 'app_donation', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DonationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $donation = new Donation();
            $donation->setAmount((int) round(((float) $data['amount']) * 100));
            $donation->setDonorName($data['donorName'] ?: null);
            $donation->setDonorEmail($data['donorEmail'] ?: null);
            $donation->setMessage($data['message'] ?: null);

            $em->persist($donation);
            $em->flush(); // Nécessaire pour avoir l'id dans les metadata Stripe

            try {
                $session = $this->stripeService->createDonationCheckoutSession($donation);

                // Mémoriser la session pour le rapprochement au webhook
                $donation->setStripeSessionId($session->id);
                $em->flush();

                return $this->redirect($session->url);
            } catch (\Throwable $e) {
                // Stripe indisponible : le don reste "pending" et sera nettoyé,
                // mais l'utilisateur n'a pas d'erreur blanche
                $this->addFlash('error', 'Le paiement est momentanément indisponible. Merci de réessayer dans quelques instants.');
            }
        }

        return $this->render('front/donation/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/success', name: 'app_donation_success')]
    public function success(): Response
    {
        // Page de retour après paiement Stripe (success_url)
        // Le webhook a déjà enregistré le don
        return $this->render('front/donation/success.html.twig');
    }

    #[Route('/cancel', name: 'app_donation_cancel')]
    public function cancel(): Response
    {
        // Retour après abandon du paiement
        return $this->render('front/donation/cancel.html.twig');
    }
}
