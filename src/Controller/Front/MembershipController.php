<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\MailService;
use App\Service\MembershipFeeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/inscription')]
class MembershipController extends AbstractController
{
    public function __construct(
        private MembershipFeeService $feeService,
        private MailService $mailService,
    ) {}

    #[Route('', name: 'app_membership_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
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

            // Stocker les consentements RGPD
            $user->setTermsAcceptedAt(new \DateTimeImmutable());
            
            // Stocker le consentement pour les photos (si coché)
            $imageConsent = $form->get('imageConsent')->getData();
            $user->setImageConsent($imageConsent);
            if ($imageConsent) {
                $user->setImageConsentAt(new \DateTimeImmutable());
            }

            // Générer un token de vérification d'email pour tous les utilisateurs
            $verificationToken = $user->generateEmailVerificationToken();
            
            // Si l'utilisateur a demandé "membre_actif", validation bureau nécessaire
            // Sinon (null), le compte sera actif après vérification de l'email
            // Note: isRegistrationConfirmed reste false jusqu'à vérification de l'email

            $em->persist($user);
            $em->flush();

            // L'inscription est acquise : un échec d'email ne doit pas la faire échouer
            // Envoyer email de vérification (M6) pour tous les utilisateurs
            // + M1: mail de confirmation à l'utilisateur
            // + M2: si membre actif, alerte au bureau
            try {
                // Générer l'URL de vérification
                $verificationUrl = $this->generateUrl('app_verify_email', [
                    'token' => $verificationToken,
                    'email' => urlencode($user->getEmail()),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                
                $this->mailService->sendEmailVerification($user, $verificationUrl);
                $this->mailService->sendRegistrationConfirmation($user);

                if ($user->needsBureauValidation()) {
                    $this->mailService->sendMembershipRequestAlert($user);
                }
            } catch (\Throwable $e) {
                // Log pour monitoring, mais l'utilisateur continue son parcours
                $this->addFlash('warning', 'Ton inscription est bien enregistrée, mais l\'envoi des emails a échoué.');
            }

            // Redirection vers page de vérification d'email
            return $this->redirectToRoute('app_check_email');
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

    #[Route('/verification-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        // Page pour informer l'utilisateur de vérifier son email
        return $this->render('front/membership/check_email.html.twig');
    }

    #[Route('/verifier-email/{token}/{email}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        string $token,
        string $email,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        $user = $userRepository->findOneBy(['email' => urldecode($email)]);
        
        if (!$user) {
            $this->addFlash('error', 'Aucun utilisateur trouvé avec cette adresse email.');
            return $this->redirectToRoute('app_login');
        }
        
        if (!$user->isEmailVerificationTokenValid($token)) {
            $this->addFlash('error', 'Le lien de vérification est invalide ou a expiré. Un nouveau lien t\'a été envoyé.');
            
            // Générer un nouveau token et renvoyer l'email
            $newToken = $user->generateEmailVerificationToken();
            $em->persist($user);
            $em->flush();
            
            try {
                $verificationUrl = $this->generateUrl('app_verify_email', [
                    'token' => $newToken,
                    'email' => urlencode($user->getEmail()),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                $this->mailService->sendEmailVerification($user, $verificationUrl);
                $this->addFlash('info', 'Un nouveau lien de vérification t\'a été envoyé.');
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Un nouveau lien a été généré mais l\'envoi a échoué.');
            }
            
            return $this->redirectToRoute('app_check_email');
        }
        
        // Marquer l'email comme vérifié
        $user->setIsRegistrationConfirmed(true);
        $user->setRegistrationValidatedAt(new \DateTimeImmutable());
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationTokenExpiresAt(null);
        
        // Si l'utilisateur n'a pas besoin de validation bureau, activer immédiatement
        if (!$user->needsBureauValidation()) {
            $user->setOfficeFunction(\App\Enum\OfficeFunction::ActiveMember);
        }
        
        $em->persist($user);
        $em->flush();
        
        $this->addFlash('success', 'Ton adresse email a été vérifiée avec succès !');
        
        // Redirection selon le statut
        if ($user->needsBureauValidation()) {
            return $this->redirectToRoute('app_membership_wait_validation');
        } else {
            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/mot-de-passe-oublie', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            $user = $userRepository->findOneBy(['email' => $email]);
            
            if ($user) {
                // Générer un token de reset
                $resetToken = $user->generatePasswordResetToken();
                $em->persist($user);
                $em->flush();
                
                // Envoyer l'email de reset
                $resetUrl = $this->generateUrl('app_reset_password', [
                    'token' => $resetToken,
                    'email' => urlencode($user->getEmail()),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                
                try {
                    $this->mailService->sendPasswordReset($user, $resetUrl);
                } catch (\Throwable $e) {
                    $this->addFlash('warning', 'Un problème est survenu lors de l\'envoi de l\'email.');
                }
                
                $this->addFlash('success', 'Si ton adresse email est enregistrée, un lien de réinitialisation t\'a été envoyé.');
            } else {
                // Ne pas révéler que l'email n'existe pas (pour la sécurité)
                $this->addFlash('success', 'Si ton adresse email est enregistrée, un lien de réinitialisation t\'a été envoyé.');
            }
            
            return $this->redirectToRoute('app_login');
        }
        
        return $this->render('front/security/forgot_password.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}/{email}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        string $token,
        string $email,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository
    ): Response {
        $user = $userRepository->findOneBy(['email' => urldecode($email)]);
        
        if (!$user || !$user->isPasswordResetTokenValid($token)) {
            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_forgot_password');
        }
        
        if ($request->isMethod('POST')) {
            $plainPassword = $request->request->get('plainPassword');
            $confirmPassword = $request->request->get('confirmPassword');
            
            if ($plainPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->render('front/security/reset_password.html.twig', [
                    'email' => $email,
                    'token' => $token,
                ]);
            }
            
            // Valider le mot de passe (au moins 12 caractères)
            if (strlen($plainPassword) < 12) {
                $this->addFlash('error', 'Le mot de passe doit faire au moins 12 caractères.');
                return $this->render('front/security/reset_password.html.twig', [
                    'email' => $email,
                    'token' => $token,
                ]);
            }
            
            // Hasher le mot de passe et le mettre à jour
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            
            // Invalider le token
            $user->setPasswordResetToken(null);
            $user->setPasswordResetTokenExpiresAt(null);
            
            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success', 'Ton mot de passe a été réinitialisé avec succès ! Tu peux maintenant te connecter.');
            return $this->redirectToRoute('app_login');
        }
        
        return $this->render('front/security/reset_password.html.twig', [
            'email' => $email,
            'token' => $token,
        ]);
    }


}
