<?php

namespace App\Controller\Admin;

use App\Entity\Donation;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Module Dons — backoffice.
 *
 * Le parcours donateur (formulaire front -> Stripe -> webhook) existait déjà ;
 * ce contrôleur donne au bureau la visibilité qui manquait : liste, filtres
 * par statut, totaux pour la trésorerie et l'assemblée générale, export CSV.
 *
 * Totaux : brut (montant donné par le donateur) et net (après frais Stripe),
 * pour la comptabilité de l'association.
 *
 * Accès : ENTITY_TREASURY (bureau étendu, trésorier inclus).
 * Les dons sont des données financières ET des données personnelles
 * (nom, email du donateur) : pas d'accès membre simple.
 */
#[IsGranted('ENTITY_TREASURY')]
#[Route('/admin/donation', name: 'app_admin_donation_')]
class DonationController extends AbstractController
{
    /** Moyens de paiement acceptés hors ligne (traçables dans stripe_session_id) */
    private const OFFLINE_METHODS = ['especes', 'cheque', 'virement'];

    public function __construct(
        private DonationRepository $donations,
        private EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        if (!\in_array($status, [Donation::STATUS_PAID, Donation::STATUS_PENDING, Donation::STATUS_FAILED], true)) {
            $status = null;
        }

        $year = (int) (new \DateTimeImmutable())->format('Y');

        return $this->render('admin/donation/index.html.twig', [
            'donations' => $this->donations->findForAdmin($status),
            'status' => $status,
            'counts' => $this->donations->countsByStatus(),
            'totalPaidThisYear' => $this->donations->getPaidTotalForYear($year),
            'totalPaidAllTime' => $this->donations->getPaidTotal(),
            'totalNetThisYear' => $this->donations->getNetTotalForYear($year),
            'totalNetAllTime' => $this->donations->getNetTotal(),
        ]);
    }

    /**
     * Encaisser un don hors-ligne (espèces, chèque, virement) : don récolté
     * en main propre (collecte événement, boîte à dons, chèque papier...).
     *
     * Réplique côté base ce que fait le webhook Stripe post-paiement :
     * don payé, zéro frais (net = brut), traçabilité via stripe_session_id
     * préfixé MANUEL- pour le distinguer des sessions Checkout réelles.
     *
     * Accès : ENTITY_CASH (trésorier et président uniquement), plus strict
     * que l'ENTITY_TREASURY de la classe : enregistrer une entrée d'argent
     * est l'acte du trésorier.
     */
    #[Route('/hors-ligne', name: 'offline', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_CASH')]
    public function offline(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            $amountInput = trim((string) $request->request->get('amount', ''));
            $method = trim((string) $request->request->get('method', ''));
            $name = trim((string) $request->request->get('donor_name', ''));
            $email = trim((string) $request->request->get('donor_email', ''));
            $message = trim((string) $request->request->get('message', ''));

            if (!$this->isCsrfTokenValid('offline_donation', $token)) {
                $this->addFlash('error', 'Jeton de sécurité invalide, réessaie.');
                return $this->redirectToRoute('app_admin_donation_offline');
            }

            if (!\in_array($method, self::OFFLINE_METHODS, true)) {
                $this->addFlash('error', 'Moyen de paiement invalide.');
                return $this->redirectToRoute('app_admin_donation_offline');
            }

            // Montant en euros (virgule ou point décimal acceptés) -> centimes
            $amountCents = (int) round(((float) str_replace(',', '.', $amountInput)) * 100);
            if ($amountInput === '' || $amountCents <= 0) {
                $this->addFlash('error', 'Indique un montant supérieur à zéro.');
                return $this->redirectToRoute('app_admin_donation_offline');
            }

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $this->addFlash('error', 'L’adresse email du donateur n’est pas valide.');
                return $this->redirectToRoute('app_admin_donation_offline');
            }

            $donation = new Donation();
            $donation->setAmount($amountCents);
            // Encaissement en main propre : aucun frais de paiement, net = brut.
            $donation->setFeeAmount(0);
            $donation->setNetAmount($amountCents);
            $donation->setDonorName($name !== '' ? $name : null);
            $donation->setDonorEmail($email !== '' ? $email : null);
            $donation->setMessage($message !== '' ? $message : null);
            $donation->setPaymentStatus(Donation::STATUS_PAID);
            $donation->setStripeSessionId(sprintf(
                'MANUEL-%s-%s',
                $method,
                (new \DateTimeImmutable())->format('Ymd-His')
            ));

            $this->em->persist($donation);
            $this->em->flush();

            $this->addFlash('success', sprintf(
                'Don de %s € encaissé (%s) et enregistré.',
                number_format($amountCents / 100, 2, ',', ' '),
                ['especes' => 'espèces', 'cheque' => 'chèque', 'virement' => 'virement'][$method]
            ));

            return $this->redirectToRoute('app_admin_donation_index');
        }

        return $this->render('admin/donation/offline.html.twig');
    }
}
