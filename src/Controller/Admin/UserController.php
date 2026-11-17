<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\OfficeFunction;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user', name: 'app_admin_user_')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $users->findBy([], ['lastName' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_CREATE')]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($this->hasher->hashPassword($user, $plain));
            $user->setRoles($form->get('isAdmin')->getData() ? ['ROLE_ADMIN'] : ['ROLE_MEMBER']);

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'user' => $user,
            'title' => 'Nouvel utilisateur',
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_EDIT')]
    public function edit(Request $request, User $user): Response
    {
        // État d'origine : handleRequest() modifie déjà l'entité (champs mappés),
        // on sauvegarde donc les valeurs initiales AVANT traitement du formulaire.
        $originalFunction = $user->getOfficeFunction();
        $originalEmail = $user->getEmail();
        $originalIsAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true);

        $form = $this->createForm(UserType::class, $user, ['current_roles' => $user->getRoles()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $me = $this->getUser();

            // Ne pas permettre de retirer son propre ROLE_ADMIN
            if ($user === $me && !$form->get('isAdmin')->getData()) {
                $this->addFlash('warning', 'Vous ne pouvez pas retirer votre propre rôle admin.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            // Anti auto-promotion : on ne change pas sa propre fonction de bureau
            if ($user === $me && $form->get('officeFunction')->getData() !== $originalFunction) {
                $user->setOfficeFunction($originalFunction);
                $this->addFlash('warning', 'Vous ne pouvez pas modifier votre propre fonction de bureau.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
            }

            // Compte du président : seul le président (ou un super-admin)
            // peut toucher email, mot de passe ou flag admin.
            // Le bureau garde le droit de changer la fonction (traduction
            // d'un vote en assemblée générale), mais pas de prendre
            // le contrôle du compte lui-même.
            $iAmPresident = $me->getOfficeFunction() === OfficeFunction::President;
            $iAmSuperAdmin = \in_array('ROLE_SUPER_ADMIN', $me->getRoles(), true);
            if ($originalFunction === OfficeFunction::President && !$iAmPresident && !$iAmSuperAdmin) {
                $changedAccount = $form->get('email')->getData() !== $originalEmail
                    || $form->get('isAdmin')->getData() !== $originalIsAdmin
                    || null !== $form->get('plainPassword')->getData();

                if ($changedAccount) {
                    // Annule les changements interdits, garde le changement de fonction
                    $user->setEmail($originalEmail);
                    $user->setRoles($originalIsAdmin ? ['ROLE_ADMIN'] : ['ROLE_MEMBER']);
                    $this->addFlash('warning', 'Seul le président peut modifier l\'email, le mot de passe ou le rôle admin du président. La fonction peut être changée pour traduire un vote.');
                    return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()]);
                }
            }

            $user->setRoles($form->get('isAdmin')->getData() ? ['ROLE_ADMIN'] : ['ROLE_MEMBER']);

            $plain = $form->get('plainPassword')->getData();
            if ($plain) {
                $user->setPassword($this->hasher->hashPassword($user, $plain));
            }

            $this->em->flush();
            $this->addFlash('success', 'Utilisateur modifié.');
            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'user' => $user,
            'title' => 'Modifier ' . $user->getFirstName(),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ENTITY_DELETE')]
    public function delete(Request $request, User $user): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('warning', 'Impossible de supprimer votre propre compte.');
        } elseif ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $this->em->remove($user);
            $this->em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }
}