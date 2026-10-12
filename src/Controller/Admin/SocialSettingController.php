<?php

namespace App\Controller\Admin;

use App\Entity\SocialNetworkSetting;
use App\Enum\SocialPlatform;
use App\Form\SocialSettingType;
use App\Repository\SocialNetworkSettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings/social', name: 'app_admin_social_')]
class SocialSettingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SocialNetworkSettingRepository $repo,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('ENTITY_SETTINGS')]
    public function index(): Response
    {
        // Synchronise les lignes avec l'enum : toute nouvelle plateforme
        // ajoutée dans SocialPlatform apparaîtra automatiquement ici.
        $settings = $this->ensureAllPlatforms();

        return $this->render('admin/settings/social.html.twig', [
            'settings' => $settings,
        ]);
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    #[IsGranted('ENTITY_SETTINGS')]
    public function toggle(Request $request, SocialNetworkSetting $setting): Response
    {
        if ($this->isCsrfTokenValid('social-toggle'.$setting->getId(), $request->getPayload()->getString('_token'))) {
            $setting->setEnabled(!$setting->isEnabled());
            $setting->setUpdatedAt(new \DateTimeImmutable());

            // Cohérence : désactiver un réseau coupe aussi la publication auto
            if (!$setting->isEnabled()) {
                $setting->setAutoPublish(false);
            }

            $this->em->flush();

            $this->addFlash('success', sprintf(
                '%s %s.',
                $setting->getPlatform()->label(),
                $setting->isEnabled() ? 'activé' : 'désactivé'
            ));
        }
        return $this->redirectToRoute('app_admin_social_index');
    }

    #[Route('/{id}/toggle-auto', name: 'toggle_auto', methods: ['POST'])]
    #[IsGranted('ENTITY_SETTINGS')]
    public function toggleAutoPublish(Request $request, SocialNetworkSetting $setting): Response
    {
        if ($this->isCsrfTokenValid('social-auto'.$setting->getId(), $request->getPayload()->getString('_token'))) {
            if (!$setting->isEnabled()) {
                $this->addFlash('warning', 'Activez d\'abord le réseau avant d\'activer la publication automatique.');
            } elseif (!$setting->getPlatform()->supportsAutoPublish()) {
                $this->addFlash('warning', sprintf(
                    '%s ne propose pas de publication automatique (partage manuel uniquement).',
                    $setting->getPlatform()->label()
                ));
            } else {
                $setting->setAutoPublish(!$setting->isAutoPublish());
                $setting->setUpdatedAt(new \DateTimeImmutable());
                $this->em->flush();

                $this->addFlash('success', sprintf(
                    'Publication automatique %s pour %s.',
                    $setting->isAutoPublish() ? 'activée' : 'désactivée',
                    $setting->getPlatform()->label()
                ));
            }
        }
        return $this->redirectToRoute('app_admin_social_index');
    }

    #[Route('/{id}/external-id', name: 'external_id', methods: ['POST'])]
    #[IsGranted('ENTITY_SETTINGS')]
    public function updateExternalId(Request $request, SocialNetworkSetting $setting): Response
    {
        if ($this->isCsrfTokenValid('social-ext'.$setting->getId(), $request->getPayload()->getString('_token'))) {
            $externalId = trim($request->getPayload()->getString('external_id'));

            if ($externalId === '') {
                $setting->setExternalId(null);
                $this->addFlash('success', sprintf('Identifiant %s supprimé.', $setting->getPlatform()->label()));
            } else {
                $setting->setExternalId($externalId);
                $this->addFlash('success', sprintf('Identifiant %s enregistré.', $setting->getPlatform()->label()));
            }

            $setting->setUpdatedAt(new \DateTimeImmutable());
            $this->em->flush();
        }
        return $this->redirectToRoute('app_admin_social_index');
    }

    // =========================================
    // MÉTHODES PRIVÉES
    // =========================================

    /**
     * Garantit qu'une ligne existe pour chaque valeur de l'enum.
     * Idempotent : ne crée que les lignes manquantes.
     *
     * @return array<SocialNetworkSetting> Indexé par nom de plateforme
     */
    private function ensureAllPlatforms(): array
    {
        $settings = [];
        foreach ($this->repo->findAll() as $setting) {
            $settings[$setting->getPlatform()->value] = $setting;
        }

        $created = false;
        foreach (SocialPlatform::cases() as $platform) {
            if (!isset($settings[$platform->value])) {
                $setting = new SocialNetworkSetting();
                $setting->setPlatform($platform);
                $this->em->persist($setting);
                $settings[$platform->value] = $setting;
                $created = true;
            }
        }

        if ($created) {
            $this->em->flush();
        }

        return $settings;
    }
}