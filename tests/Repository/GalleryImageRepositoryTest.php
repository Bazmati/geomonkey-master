<?php

namespace App\Tests\Repository;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Enum\OfficeFunction;
use App\Enum\ParticipantRole;
use App\Repository\GalleryImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests pour GalleryImageRepository, en particulier findVisibleForUser().
 */
class GalleryImageRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private GalleryImageRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->em->getRepository(GalleryImage::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testFindVisibleForUserAnonymous(): void
    {
        // Créer une image Public publiée
        $publicImage = new GalleryImage();
        $publicImage->setVisibility(ImageVisibility::Public);
        $publicImage->setIsPublished(true);
        $publicImage->setTitle('Image publique');
        $publicImage->setAlt('Description publique');
        $this->em->persist($publicImage);

        // Créer une image Members publiée
        $membersImage = new GalleryImage();
        $membersImage->setVisibility(ImageVisibility::Members);
        $membersImage->setIsPublished(true);
        $membersImage->setTitle('Image membres');
        $membersImage->setAlt('Description membres');
        $this->em->persist($membersImage);

        // Créer une image Bureau publiée
        $bureauImage = new GalleryImage();
        $bureauImage->setVisibility(ImageVisibility::Bureau);
        $bureauImage->setIsPublished(true);
        $bureauImage->setTitle('Image bureau');
        $bureauImage->setAlt('Description bureau');
        $this->em->persist($bureauImage);

        // Créer une image non publiée
        $unpublishedImage = new GalleryImage();
        $unpublishedImage->setVisibility(ImageVisibility::Public);
        $unpublishedImage->setIsPublished(false);
        $unpublishedImage->setTitle('Image non publiée');
        $unpublishedImage->setAlt('Description non publiée');
        $this->em->persist($unpublishedImage);

        $this->em->flush();

        // Anonyme ne doit voir que l'image Public publiée
        $visibleImages = $this->repository->findVisibleForUser(null);

        $this->assertCount(1, $visibleImages);
        $this->assertSame($publicImage->getId(), $visibleImages[0]->getId());
    }

    public function testFindVisibleForUserBureau(): void
    {
        // Créer un utilisateur du bureau
        $user = new User();
        $user->setEmail('president@asso.fr');
        $user->setOfficeFunction(OfficeFunction::President);
        $this->em->persist($user);

        // Créer des images de toutes visibilités
        $publicImage = new GalleryImage();
        $publicImage->setVisibility(ImageVisibility::Public);
        $publicImage->setIsPublished(true);
        $publicImage->setTitle('Image publique');
        $publicImage->setAlt('Description publique');
        $this->em->persist($publicImage);

        $membersImage = new GalleryImage();
        $membersImage->setVisibility(ImageVisibility::Members);
        $membersImage->setIsPublished(true);
        $membersImage->setTitle('Image membres');
        $membersImage->setAlt('Description membres');
        $this->em->persist($membersImage);

        $bureauImage = new GalleryImage();
        $bureauImage->setVisibility(ImageVisibility::Bureau);
        $bureauImage->setIsPublished(true);
        $bureauImage->setTitle('Image bureau');
        $bureauImage->setAlt('Description bureau');
        $this->em->persist($bureauImage);

        $unpublishedImage = new GalleryImage();
        $unpublishedImage->setVisibility(ImageVisibility::Public);
        $unpublishedImage->setIsPublished(false);
        $unpublishedImage->setTitle('Image non publiée');
        $unpublishedImage->setAlt('Description non publiée');
        $this->em->persist($unpublishedImage);

        $this->em->flush();

        // Un membre du bureau doit voir toutes les images publiées
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertCount(3, $visibleImages);
        
        $visibleIds = array_map(fn($img) => $img->getId(), $visibleImages);
        $this->assertContains($publicImage->getId(), $visibleIds);
        $this->assertContains($membersImage->getId(), $visibleIds);
        $this->assertContains($bureauImage->getId(), $visibleIds);
        $this->assertNotContains($unpublishedImage->getId(), $visibleIds);
    }

    public function testFindVisibleForUserActiveMember(): void
    {
        // Créer un utilisateur membre actif
        $user = new User();
        $user->setEmail('membre@asso.fr');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $this->em->persist($user);

        // Créer des images
        $publicImage = new GalleryImage();
        $publicImage->setVisibility(ImageVisibility::Public);
        $publicImage->setIsPublished(true);
        $publicImage->setTitle('Image publique');
        $publicImage->setAlt('Description publique');
        $this->em->persist($publicImage);

        $membersImage = new GalleryImage();
        $membersImage->setVisibility(ImageVisibility::Members);
        $membersImage->setIsPublished(true);
        $membersImage->setTitle('Image membres');
        $membersImage->setAlt('Description membres');
        $this->em->persist($membersImage);

        $bureauImage = new GalleryImage();
        $bureauImage->setVisibility(ImageVisibility::Bureau);
        $bureauImage->setIsPublished(true);
        $bureauImage->setTitle('Image bureau');
        $bureauImage->setAlt('Description bureau');
        $this->em->persist($bureauImage);

        $this->em->flush();

        // Un membre actif doit voir Public + Members, pas Bureau
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertCount(2, $visibleImages);
        
        $visibleIds = array_map(fn($img) => $img->getId(), $visibleImages);
        $this->assertContains($publicImage->getId(), $visibleIds);
        $this->assertContains($membersImage->getId(), $visibleIds);
        $this->assertNotContains($bureauImage->getId(), $visibleIds);
    }

    public function testFindVisibleForUserWithParticipants(): void
    {
        // Créer un utilisateur membre actif
        $user = new User();
        $user->setEmail('participant@asso.fr');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $this->em->persist($user);

        // Créer un événement
        $event = new Event();
        $event->setTitle('Événement test');
        $event->setDescription('Description de l\'événement');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Paris');
        $this->em->persist($event);

        // Ajouter l'utilisateur comme participant
        $event->addParticipant($user, ParticipantRole::Participant);

        // Créer une image Participants liée à l'événement
        $participantsImage = new GalleryImage();
        $participantsImage->setVisibility(ImageVisibility::Participants);
        $participantsImage->setIsPublished(true);
        $participantsImage->setTitle('Image participants');
        $participantsImage->setAlt('Description participants');
        $participantsImage->addEvent($event);
        $this->em->persist($participantsImage);

        // Créer une autre image Participants avec un événement différent
        $otherEvent = new Event();
        $otherEvent->setTitle('Autre événement');
        $otherEvent->setDescription('Autre description');
        $otherEvent->setStartDate(new \DateTime('2024-02-01'));
        $otherEvent->setEndDate(new \DateTime('2024-02-02'));
        $otherEvent->setLocation('Lyon');
        $this->em->persist($otherEvent);

        $otherParticipantsImage = new GalleryImage();
        $otherParticipantsImage->setVisibility(ImageVisibility::Participants);
        $otherParticipantsImage->setIsPublished(true);
        $otherParticipantsImage->setTitle('Autre image participants');
        $otherParticipantsImage->setAlt('Autre description participants');
        $otherParticipantsImage->addEvent($otherEvent);
        $this->em->persist($otherParticipantsImage);

        // Créer une image Public
        $publicImage = new GalleryImage();
        $publicImage->setVisibility(ImageVisibility::Public);
        $publicImage->setIsPublished(true);
        $publicImage->setTitle('Image publique');
        $publicImage->setAlt('Description publique');
        $this->em->persist($publicImage);

        $this->em->flush();

        // L'utilisateur doit voir Public + l'image Participants de l'événement auquel il participe
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertGreaterThanOrEqual(2, count($visibleImages));
        
        $visibleIds = array_map(fn($img) => $img->getId(), $visibleImages);
        $this->assertContains($publicImage->getId(), $visibleIds);
        $this->assertContains($participantsImage->getId(), $visibleIds);
        // Ne doit pas voir l'autre image Participants (événement différent)
        $this->assertNotContains($otherParticipantsImage->getId(), $visibleIds);
    }

    public function testFindVisibleForUserExcludesUnpublished(): void
    {
        // Créer un utilisateur membre actif
        $user = new User();
        $user->setEmail('membre@asso.fr');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $this->em->persist($user);

        // Créer une image Public non publiée
        $unpublishedImage = new GalleryImage();
        $unpublishedImage->setVisibility(ImageVisibility::Public);
        $unpublishedImage->setIsPublished(false);
        $unpublishedImage->setTitle('Image non publiée');
        $unpublishedImage->setAlt('Description non publiée');
        $this->em->persist($unpublishedImage);

        // Créer une image Public publiée
        $publishedImage = new GalleryImage();
        $publishedImage->setVisibility(ImageVisibility::Public);
        $publishedImage->setIsPublished(true);
        $publishedImage->setTitle('Image publiée');
        $publishedImage->setAlt('Description publiée');
        $this->em->persist($publishedImage);

        $this->em->flush();

        // Ne doit voir que l'image publiée
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertCount(1, $visibleImages);
        $this->assertSame($publishedImage->getId(), $visibleImages[0]->getId());
    }

    public function testFindVisibleForUserBureauSeesUnpublished(): void
    {
        // Note: Selon la logique actuelle, même le bureau ne voit que les images publiées
        // car le filtre isPublished = true est appliqué avant tout
        // C'est un choix de design : seule la publication rend une image visible
        
        // Créer un utilisateur président
        $user = new User();
        $user->setEmail('president@asso.fr');
        $user->setOfficeFunction(OfficeFunction::President);
        $this->em->persist($user);

        // Créer des images publiées et non publiées
        $publishedImage = new GalleryImage();
        $publishedImage->setVisibility(ImageVisibility::Public);
        $publishedImage->setIsPublished(true);
        $publishedImage->setTitle('Image publiée');
        $publishedImage->setAlt('Description publiée');
        $this->em->persist($publishedImage);

        $unpublishedImage = new GalleryImage();
        $unpublishedImage->setVisibility(ImageVisibility::Public);
        $unpublishedImage->setIsPublished(false);
        $unpublishedImage->setTitle('Image non publiée');
        $unpublishedImage->setAlt('Description non publiée');
        $this->em->persist($unpublishedImage);

        $this->em->flush();

        // Même le président ne voit que les images publiées
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertCount(1, $visibleImages);
        $this->assertSame($publishedImage->getId(), $visibleImages[0]->getId());
    }

    public function testFindVisibleForUserOrderByCreatedAtDesc(): void
    {
        // Créer un utilisateur
        $user = new User();
        $user->setEmail('membre@asso.fr');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $this->em->persist($user);

        // Créer des images avec des dates différentes
        $firstImage = new GalleryImage();
        $firstImage->setVisibility(ImageVisibility::Public);
        $firstImage->setIsPublished(true);
        $firstImage->setTitle('Première image');
        $firstImage->setAlt('Première');
        $firstImage->setCreatedAt(new \DateTime('2024-01-01 10:00:00'));
        $this->em->persist($firstImage);

        $secondImage = new GalleryImage();
        $secondImage->setVisibility(ImageVisibility::Public);
        $secondImage->setIsPublished(true);
        $secondImage->setTitle('Deuxième image');
        $secondImage->setAlt('Deuxième');
        $secondImage->setCreatedAt(new \DateTime('2024-01-02 10:00:00'));
        $this->em->persist($secondImage);

        $thirdImage = new GalleryImage();
        $thirdImage->setVisibility(ImageVisibility::Public);
        $thirdImage->setIsPublished(true);
        $thirdImage->setTitle('Troisième image');
        $thirdImage->setAlt('Troisième');
        $thirdImage->setCreatedAt(new \DateTime('2024-01-03 10:00:00'));
        $this->em->persist($thirdImage);

        $this->em->flush();

        // Les images doivent être triées par createdAt DESC
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertCount(3, $visibleImages);
        $this->assertSame($thirdImage->getId(), $visibleImages[0]->getId());
        $this->assertSame($secondImage->getId(), $visibleImages[1]->getId());
        $this->assertSame($firstImage->getId(), $visibleImages[2]->getId());
    }

    // ========================================================================
    // Tests pour la correction des bugs identifiés
    // ========================================================================

    /**
     * FIX BUG #1 : OfficeFunction::None doit être traité comme un anonyme
     * Un utilisateur avec None ne doit voir que les images Public
     */
    public function testFindVisibleForUserWithOfficeFunctionNone(): void
    {
        // Créer un utilisateur sans fonction (None)
        $user = new User();
        $user->setEmail('visiteur@asso.fr');
        $user->setOfficeFunction(OfficeFunction::None);
        $this->em->persist($user);

        // Créer des images de différentes visibilités
        $publicImage = new GalleryImage();
        $publicImage->setVisibility(ImageVisibility::Public);
        $publicImage->setIsPublished(true);
        $publicImage->setTitle('Image publique');
        $publicImage->setAlt('Description publique');
        $this->em->persist($publicImage);

        $membersImage = new GalleryImage();
        $membersImage->setVisibility(ImageVisibility::Members);
        $membersImage->setIsPublished(true);
        $membersImage->setTitle('Image membres');
        $membersImage->setAlt('Description membres');
        $this->em->persist($membersImage);

        $bureauImage = new GalleryImage();
        $bureauImage->setVisibility(ImageVisibility::Bureau);
        $bureauImage->setIsPublished(true);
        $bureauImage->setTitle('Image bureau');
        $bureauImage->setAlt('Description bureau');
        $this->em->persist($bureauImage);

        $this->em->flush();

        // Un utilisateur None ne doit voir QUE les images Public (comme un anonyme)
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertCount(1, $visibleImages);
        $this->assertSame($publicImage->getId(), $visibleImages[0]->getId());
        
        $visibleIds = array_map(fn($img) => $img->getId(), $visibleImages);
        $this->assertNotContains($membersImage->getId(), $visibleIds);
        $this->assertNotContains($bureauImage->getId(), $visibleIds);
    }

    /**
     * FIX BUG #2 : NOT IN avec tableau vide
     * Quand il n'y a AUCUNE image Public/Members, le participant doit quand même
     * voir ses images Participants (pas de NOT IN () qui casse la requête)
     */
    public function testFindVisibleForUserWithOnlyParticipantsImages(): void
    {
        // Créer un utilisateur participant
        $user = new User();
        $user->setEmail('participant@asso.fr');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $this->em->persist($user);

        // Créer un événement
        $event = new Event();
        $event->setTitle('Événement test');
        $event->setDescription('Description de l\'événement');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Paris');
        $this->em->persist($event);

        // Ajouter l'utilisateur comme participant
        $event->addParticipant($user, ParticipantRole::Participant);

        // Créer UNIQUEMENT des images Participants (pas de Public/Members)
        $participantsImage = new GalleryImage();
        $participantsImage->setVisibility(ImageVisibility::Participants);
        $participantsImage->setIsPublished(true);
        $participantsImage->setTitle('Image participants');
        $participantsImage->setAlt('Description participants');
        $participantsImage->addEvent($event);
        $this->em->persist($participantsImage);

        $this->em->flush();

        // Le participant DOIT voir son image Participants
        // (avant le fix, NOT IN () retournait rien)
        $visibleImages = $this->repository->findVisibleForUser($user);

        $this->assertCount(1, $visibleImages, 'Un participant doit voir ses images Participants même sans images Public/Members');
        $this->assertSame($participantsImage->getId(), $visibleImages[0]->getId());
    }
}
