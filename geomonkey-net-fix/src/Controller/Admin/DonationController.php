<?php

namespace App\Controller\Admin;

use App\Entity\Donation;
use App\Repository\DonationRepository;
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
    public function __construct(private DonationRepository $donations) {}

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
}
