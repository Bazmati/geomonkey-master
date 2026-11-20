<?php

namespace App\Controller\Admin;

use App\Entity\Membership;
use App\Entity\User;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Service\MembershipFeeService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// NB : pas de #[IsGranted] au niveau classe : l'action offline() est ouverte
// au trésorier (ENTITY_CASH) alors que le reste exige ENTITY_EDIT, qui ne
// l'inclut PAS (canEdit : président, VP, secrétaire). Un garde de classe
// s'applique à toutes les actions avant les attributs de méthode : il
// bloquerait le trésorier même avec un ENTITY_CASH en méthode. Chaque action
// porte donc son propre attribut ci-dessous.
#[Route('/admin/membership', name: 'app_admin_membership_')]
class MembershipController extends AbstractController
{
    /** Moyens de paiement acceptés hors ligne (traçables dans stripe_payment_id) */
    private const OFFLINE_METHODS = ['especes', 'cheque', 'virement'];

    public function __construct(
        private UserRepository $userRepo,
        private MembershipRepository $membershipRepository,
        private EntityManagerInterface $em,
        private MembershipFeeService $feeService,
        private StripeService $stripeService,
        private MailService $mailService,
    ) {}

    #[Route('/requests', name: 'requests')]
    #[IsGranted('ENTITY_EDIT')]
    public function requests(): Response
    {
        $pendingUsers = $this->userRepo->findPendingMembershipRequests();

        return $this->render('admin/membership/requests.html.twig', [
            'pendingUsers' => $pendingUsers,
        ]);
    }

    #[Route('/validate/{id}', name: 'validate', methods: ['POST'])]
    #[IsGranted('ENTITY_EDIT')]
    public function validate(Request $request, User $user): RedirectResponse
    {
        if (!$user->needsBureauValidation()) {
            $this->addFlash('error', 'Cette demande ne nécessite pas de validation.');
            return $this->redirectToRoute('app_admin_membership_requests');
        }

        // Vérification du CSRF token pour la validation
        if (!$this->isCsrfTokenValid('validate'.$user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_membership_requests');
        }

        // Créer une membership en attente de paiement
        $membership = new Membership();
        $membership->setUser($user);
        $membership->setIsActive(false); // Pas active jusqu'au paiement
        $membership->setStartedAt(new \DateTimeImmutable());
        // expiresAt sera défini après paiement (now + 1 year)
        $membership->setAmount($this->feeService->getCurrentAmount());
        $membership->setPaymentStatus('pending');

        $this->em->persist($membership);

        // Valider l'utilisateur (mais statut ne passe en membre_actif qu'après paiement)
        $user->setIsRegistrationConfirmed(true);
        $user->setRegistrationValidatedAt(new \DateTimeImmutable());
        // NE PAS changer officeFunction ici - ça sera fait dans le webhook après paiement

        $this->em->flush();

        // Générer le lien de paiement
        try {
            $paymentUrl = $this->stripeService->createMembershipCheckoutSession($user);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_admin_membership_requests');
        }

        // M3: Envoyer le mail de validation avec le lien de paiement
        $this->mailService->sendValidationEmail($user, $paymentUrl);

        $this->addFlash('success', sprintf(
            'Demande de %s validée ! Un mail avec le lien de paiement a été envoyé.',
            $user->getEmail()
        ));

        return $this->redirectToRoute('app_admin_membership_requests');
    }

    #[Route('/list', name: 'list')]
    // Liste des adhésions : bureau étendu (TREASURY) pour que le trésorier
    // puisse consulter les cotisations ET atterrir ici après un encaissement
    // hors-ligne (le redirect de offline() pointe sur cette page).
    #[IsGranted('ENTITY_TREASURY')]
    public function list(): Response
    {
        $memberships = $this->membershipRepository->findAllWithUsers();

        $stats = [
            'total' => \count($memberships),
            'active' => 0,
            'collected' => 0, // en euros
        ];
        foreach ($memberships as $membership) {
            if ($membership->isActive() && $membership->getPaymentStatus() === 'paid') {
                ++$stats['active'];
                $stats['collected'] += $membership->getAmount();
            }
        }

        return $this->render('admin/membership/list.html.twig', [
            'memberships' => $memberships,
            'stats' => $stats,
        ]);
    }

    /**
     * Encaissement hors-ligne d'une cotisation (espèces, chèque, virement) :
     * le bureau encaisse en main propre (AG, événement...) sans passer par Stripe.
     *
     * Réplique la logique post-paiement du webhook Stripe (createOrUpdateMembership) :
     * - désactive les memberships existants du membre
     * - crée une membership paid valable 1 an
     * - passe le visiteur en membre_actif, confirme l'inscription
     * - envoie le mail M4 de confirmation
     *
     * Traçabilité : stripe_payment_id = 'MANUEL-<moyen>-<date>' (pas de migration
     * de schéma nécessaire, la colonne existe déjà).
     */
    #[Route('/hors-ligne', name: 'offline', methods: ['GET', 'POST'])]
    // Encaissement hors-ligne : trésorier et président uniquement.
    // Surcharge l'ENTITY_EDIT de la classe (qui n'inclut PAS le trésorier).
    #[IsGranted('ENTITY_CASH')]
    public function offline(Request $request): Response
    {
        // Montant par défaut : le tarif courant si défini
        $defaultAmount = 15.00;
        try {
            $defaultAmount = $this->feeService->getCurrentAmountInEuros();
        } catch (\RuntimeException) {
            // aucun tarif défini : fallback affiché, l'agent saisit le montant réel
        }

        if (!$request->isMethod('POST')) {
            return $this->render('admin/membership/offline.html.twig', [
                'users' => $this->userRepo->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC']),
                'default_amount' => $defaultAmount,
                'methods' => self::OFFLINE_METHODS,
            ]);
        }

        // ----- Traitement du formulaire -----
        if (!$this->isCsrfTokenValid('offline_payment', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_membership_offline');
        }

        /** @var User|null $member */
        $member = $this->userRepo->find((int) $request->request->get('user_id'));
        if ($member === null) {
            $this->addFlash('error', 'Membre introuvable.');
            return $this->redirectToRoute('app_admin_membership_offline');
        }

        $method = (string) $request->request->get('method');
        if (!in_array($method, self::OFFLINE_METHODS, true)) {
            $this->addFlash('error', 'Moyen de paiement invalide.');
            return $this->redirectToRoute('app_admin_membership_offline');
        }

        // Montant saisi en euros (virgule ou point acceptés)
        $amountCents = (int) round(((float) str_replace(',', '.', (string) $request->request->get('amount'))) * 100);
        if ($amountCents <= 0) {
            $this->addFlash('error', 'Merci de saisir un montant valide.');
            return $this->redirectToRoute('app_admin_membership_offline');
        }

        // Désactiver les memberships existants du membre (même règle que le webhook)
        foreach ($member->getMemberships() as $existing) {
            $existing->setIsActive(false);
        }

        $nowImmutable = new \DateTimeImmutable();
        $expiresAt = $nowImmutable->add(new \DateInterval('P1Y'));

        $membership = new Membership();
        $membership->setUser($member);
        $membership->setAmount($amountCents);
        $membership->setIsActive(true);
        $membership->setStartedAt($nowImmutable);
        $membership->setExpiresAt($expiresAt);
        $membership->setStripePaymentId(sprintf(
            'MANUEL-%s-%s',
            $method,
            $nowImmutable->format('Ymd-His')
        ));
        $membership->setPaymentStatus('paid');

        $member->addMembership($membership);

        // Si le membre était un simple visiteur -> membre actif (règle du webhook)
        if ($member->getOfficeFunction() === \App\Enum\OfficeFunction::None) {
            $member->setOfficeFunction(\App\Enum\OfficeFunction::ActiveMember);
        }

        $member->setIsRegistrationConfirmed(true);
        $member->setRegistrationValidatedAt($nowImmutable);
        $member->setMembershipValidUntil(\DateTime::createFromImmutable($expiresAt));

        $this->em->persist($membership);
        $this->em->flush();

        // M4 : mail de confirmation (non bloquant, même principe que le webhook)
        try {
            $this->mailService->sendMembershipConfirmedEmail($member);
        } catch (\Throwable) {
            $this->addFlash('warning', sprintf(
                'Cotisation de %s encaissée (%s), mais l\'email de confirmation n\'a pas pu être envoyé.',
                $member->getEmail(),
                $this->methodLabel($method)
            ));
            return $this->redirectToRoute('app_admin_membership_list');
        }

        $this->addFlash('success', sprintf(
            'Cotisation de %s encaissée hors-ligne (%s) : adhésion valable jusqu\'au %s.',
            $member->getEmail(),
            $this->methodLabel($method),
            $expiresAt->format('d/m/Y')
        ));

        return $this->redirectToRoute('app_admin_membership_list');
    }

    /** Libellé lisible du moyen de paiement hors-ligne */
    private function methodLabel(string $method): string
    {
        return match ($method) {
            'especes' => 'espèces',
            'cheque' => 'chèque',
            'virement' => 'virement',
            default => $method,
        };
    }
}
