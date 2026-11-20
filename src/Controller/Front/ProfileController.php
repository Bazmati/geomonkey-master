<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Page « Mon profil » (front) : consultation et édition des informations
 * personnelles non sensibles par l'utilisateur connecté.
 *
 * v1 : prénom, nom et consentement photos (RGPD) modifiables.
 * L'email (identifiant de connexion) et le mot de passe restent hors de
 * cette page : l'email via le bureau, le mot de passe via le flux
 * « Mot de passe oublié » de la page de connexion.
 * La fonction bureau, les rôles et les adhésions ne sont jamais
 * modifiables par l'utilisateur lui-même.
 */
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route(path: '/profil', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function show(): Response
    {
        return $this->render('front/profile/show.html.twig');
    }

    #[Route(path: '/profil/modifier', name: 'app_profile_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // État du consentement avant soumission, pour horodater le changement (RGPD).
        $hadImageConsent = $user->getImageConsent();

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traçabilité RGPD du consentement photo :
            // horodatage à l'activation, effacement au retrait.
            if ($user->getImageConsent() && !$hadImageConsent) {
                $user->setImageConsentAt(new \DateTimeImmutable());
            } elseif (!$user->getImageConsent() && $hadImageConsent) {
                $user->setImageConsentAt(null);
            }

            $em->flush();

            $this->addFlash('success', $this->translator->trans('flash.profile.updated'));

            return $this->redirectToRoute('app_profile');
        }

        // NB : passer $form (et non $form->createView()) à render() permet à
        // Symfony de répondre automatiquement 422 quand le formulaire soumis
        // est invalide (comportement attendu par ProfileControllerTest).
        return $this->render('front/profile/edit.html.twig', [
            'form' => $form,
        ]);
    }
}
