<?php

namespace App\Controller\Admin;

use App\Entity\BlogPost;
use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Form\BlogPostType;
use App\Repository\BlogPostRepository;
use App\Repository\GalleryImageRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/blog', name: 'app_admin_blog_')]
class BlogPostController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ImageManager $imageManager,
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    #[IsGranted('ENTITY_VIEW')]
    public function index(BlogPostRepository $repo): Response
    {
        return $this->render('admin/blog/index.html.twig', [
            'posts' => $repo->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_CREATE')]
    public function new(Request $request): Response
    {
        $post = new BlogPost();
        $post->setAuthor($this->getUser());

        return $this->handleForm($request, $post, true);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('ENTITY_EDIT')]
    public function edit(Request $request, BlogPost $post): Response
    {
        return $this->handleForm($request, $post);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ENTITY_DELETE')]
    public function delete(Request $request, BlogPost $post): Response
    {
        if ($this->isCsrfTokenValid('delete'.$post->getId(), $request->getPayload()->getString('_token'))) {
            // Une entité BlogPost non persistée ne doit jamais arriver ici,
            // mais on protège le cas d'un id null par robustesse.
            $this->em->remove($post);
            $this->em->flush();
            $this->addFlash('success', 'Article supprimé avec succès.');
        }
        return $this->redirectToRoute('app_admin_blog_index');
    }

    /**
     * Mise en ligne / retrait de la ligne — PRÉSIDENT UNIQUEMENT.
     * C'est ici que l'article devient public et déclenchera (plus tard)
     * la diffusion sur les réseaux sociaux configurés dans Paramètres.
     */
    #[Route('/{id}/toggle-publish', name: 'toggle_publish', methods: ['POST'])]
    #[IsGranted('ENTITY_PUBLISH')]
    public function togglePublish(Request $request, BlogPost $post): Response
    {
        if ($this->isCsrfTokenValid('publish'.$post->getId(), $request->getPayload()->getString('_token'))) {
            if ($post->isPublished()) {
                $post->unpublish();
                $this->addFlash('success', 'Article retiré de la ligne (il reste en brouillon).');
            } else {
                $post->publish();
                $this->addFlash('success', 'Article mis en ligne : il est désormais visible publiquement.');

                // TODO (étape réseaux sociaux) : dispatch d'un message Messenger
                // SocialPublishMessage($post->getId()) pour diffusion asynchrone
                // sur les réseaux activés dans Paramètres → Réseaux sociaux.
            }

            $this->em->flush();
        }
        return $this->redirectToRoute('app_admin_blog_index');
    }

    // =========================================
    // MÉTHODES PRIVÉES
    // =========================================

    private function handleForm(Request $request, BlogPost $post, bool $isNew = false): Response
    {
        $form = $this->createForm(BlogPostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Slug : généré à la création uniquement (les URLs restent stables ensuite)
            if ($isNew && ($post->getSlug() === null || $post->getSlug() === '')) {
                $post->computeSlug();
            }

            // Le rédacteur courant devient auteur si aucun auteur n'est déjà posé
            if ($post->getAuthor() === null) {
                $user = $this->getUser();
                if ($user instanceof User) {
                    $post->setAuthor($user);
                }
            }

            // Image de couverture téléversée : crée une GalleryImage « Publique »,
            // NON publiée (même option A que le CRUD présentation)
            $imageFile = $form->get('newImageFile')->getData();
            if ($imageFile instanceof UploadedFile) {
                $image = new GalleryImage();
                $image->setTitle($post->getTitle() ?? 'Image de couverture');
                $image->setAlt(trim((string) $form->get('newImageAlt')->getData()));
                $image->setVisibility(ImageVisibility::Public);
                $image->giveConsent();
                $image->setConsentDetail(sprintf(
                    'Image ajoutée via l\'article de blog « %s »',
                    $post->getTitle() ?? 'sans titre'
                ));
                $image->setUploadedBy($this->getUser());
                $image->setIsPublished(false);

                // ⚠️ Le flush initial exige image NOT NULL : placeholder temporaire,
                // écrasé par l'UUID juste après dans upload().
                $image->setImage('pending-' . bin2hex(random_bytes(8)));

                // ⚠️ ORDRE CRUCIAL : flusher D'ABORD pour obtenir l'ID,
                // puis uploader → uploads/gallery/{id}/...
                $this->em->persist($image);
                $this->em->flush();

                $this->imageManager->upload($image, $imageFile);
                $post->setImage($image->getImage());
                $post->addGalleryImage($image);

                $this->addFlash('warning',
                    'Image de couverture ajoutée à la galerie en attente de publication. ' .
                    'Un président doit la publier dans « Gestion de la galerie » pour qu\'elle soit visible.'
                );
            }

            if ($isNew) {
                $this->em->persist($post);
            }
            $this->em->flush();

            $this->addFlash('success', $isNew
                ? 'Article créé avec succès. Il reste en brouillon jusqu\'à publication par le président.'
                : 'Article modifié avec succès.');
            return $this->redirectToRoute('app_admin_blog_index');
        }

        return $this->render('admin/blog/form.html.twig', [
            'form' => $form,
            'post' => $post,
            'title' => $isNew ? 'Nouvel article' : 'Modifier l\'article',
        ]);
    }
}