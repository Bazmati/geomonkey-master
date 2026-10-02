<?php

namespace App\Controller\Admin;

use App\Entity\Membership;
use App\Entity\User;
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

#[IsGranted('ENTITY_EDIT')] // Bureau (président + secrétaire)
#[Route('/admin/membership', name: 'app_admin_membership_')]
class MembershipController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
        private MembershipFeeService $feeService,
        private StripeService $stripeService,
        private MailService $mailService,
    ) {}

    #[Route('/requests', name: 'requests')]
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
    public function list(): Response
    {
        // TODO: Lister toutes les adhésions
        return $this->render('admin/membership/list.html.twig');
    }
}
