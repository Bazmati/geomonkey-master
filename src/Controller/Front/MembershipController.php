<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\MembershipFeeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inscription')]
class MembershipController extends AbstractController
{
    public function __construct(
        private MembershipFeeService $feeService,
    ) {}

    #[Route('', name: 'app_membership_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $authenticator,
    ): Response {
        try {
            $membershipFee = $this->feeService->getCurrentAmountInEuros();
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le mot de passe en clair depuis le formulaire (non mappé)
            $plainPassword = $form->get('plainPassword')->getData();
            
            // Hash du mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Définir les rôles par défaut
            $user->setRoles(['ROLE_USER']);

            // Si l'utilisateur a demandé "membre_actif", validation bureau nécessaire
            // Sinon (null), compte actif immédiatement
            if (!$user->needsBureauValidation()) {
                $user->setIsRegistrationConfirmed(true);
                $user->setRegistrationValidatedAt(new \DateTimeImmutable());
            }

            $em->persist($user);
            $em->flush();

            // Envoyer le mail de confirmation
            $this->sendRegistrationMail($user);

            // Redirection selon le statut
            if ($user->needsBureauValidation()) {
                return $this->redirectToRoute('app_membership_wait_validation');
            } else {
                $this->addFlash('success', 'Ton inscription est confirmée ! Tu peux maintenant te connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('front/membership/register.html.twig', [
            'form' => $form->createView(),
            'membershipFee' => $membershipFee,
        ]);
    }

    #[Route('/attente-validation', name: 'app_membership_wait_validation')]
    public function waitValidation(): Response
    {
        return $this->render('front/membership/wait_validation.html.twig');
    }

    #[Route('/paiement-requis', name: 'app_membership_payment_required')]
    public function paymentRequired(): Response
    {
        // Cette page sera affichée après validation bureau
        // Le lien de paiement sera envoyé par mail
        return $this->render('front/membership/payment_required.html.twig');
    }

    #[Route('/success', name: 'app_membership_success')]
    public function success(Request $request): Response
    {
        // Page de retour après paiement Stripe (success_url)
        // Le webhook a déjà traité le paiement
        return $this->render('front/membership/success.html.twig');
    }

    #[Route('/cancel', name: 'app_membership_cancel')]
    public function cancel(): Response
    {
        // Page de retour après annulation du paiement
        return $this->render('front/membership/cancel.html.twig');
    }

    /**
     * Envoie le mail de confirmation d'inscription
     */
    private function sendRegistrationMail(User $user): void
    {
        // TODO: Implémenter l'envoi du mail avec MailerInterface
        // Pour l'instant, on simule avec un flash message
        $this->addFlash('info', 'Un email de confirmation a été envoyé à ' . $user->getEmail());
    }
}
