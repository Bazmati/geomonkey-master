<?php

namespace App\Tests\Security;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Enum\OfficeFunction;
use App\Repository\EventParticipantRepository;
use App\Security\GalleryImageVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class GalleryImageVoterTest extends TestCase
{
    private GalleryImageVoter $voter;
    private EventParticipantRepository $participantRepository;

    protected function setUp(): void
    {
        $this->participantRepository = $this->createMock(EventParticipantRepository::class);
        $this->voter = new GalleryImageVoter($this->participantRepository);
    }

    private function createToken(User $user): TestBrowserToken
    {
        return new TestBrowserToken($user->getRoles(), $user);
    }

    // ========================================================================
    // Tests pour VIEW avec différentes visibilités
    // ========================================================================

    public function testPublicImageViewAllowedForAnonymous(): void
    {
        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Public);

        $token = new TestBrowserToken([]); // Anonyme

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPublicImageViewAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Public);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testMembersImageViewDeniedForAnonymous(): void
    {
        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Members);

        $token = new TestBrowserToken([]); // Anonyme

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testMembersImageViewAllowedForActiveMember(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Members);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testMembersImageViewDeniedForNone(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);
        $token = $this->createToken($user);

        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Members);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testBureauImageViewDeniedForActiveMember(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Bureau);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testBureauImageViewAllowedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Bureau);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testBureauImageViewAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Bureau);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ========================================================================
    // Tests pour Participants (avec EventParticipant)
    // ========================================================================

    public function testParticipantsImageViewAllowedForParticipant(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $event = new Event();
        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Participants);
        $galleryImage->addEvent($event);

        // Configurer le repository pour retourner true
        $this->participantRepository->method('userParticipatesIn')
            ->with($user, [$event])
            ->willReturn(true);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testParticipantsImageViewDeniedForNonParticipant(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $event = new Event();
        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Participants);
        $galleryImage->addEvent($event);

        // Configurer le repository pour retourner false
        $this->participantRepository->method('userParticipatesIn')
            ->with($user, [$event])
            ->willReturn(false);

        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    /**
     * Test que les images Participants sans événements liés sont invisibles
     */
    public function testParticipantsImageViewDeniedWhenNoEventsLinked(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $galleryImage = new GalleryImage();
        $galleryImage->setVisibility(ImageVisibility::Participants);
        // Pas d'événements liés

        // Le repository ne doit pas être appelé car events est vide
        $result = $this->voter->vote($token, $galleryImage, [GalleryImageVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ========================================================================
    // Tests pour les actions (CREATE, EDIT, DELETE, PUBLISH)
    // ========================================================================

    public function testCreateAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateAllowedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateAllowedForTreasurer(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateDeniedForActiveMember(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditAllowedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditDeniedForTreasurer(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeleteDeniedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeleteDeniedForTreasurer(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // ========================================================================
    // Tests pour PUBLISH
    // ========================================================================

    public function testPublishAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::PUBLISH]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testPublishDeniedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::PUBLISH]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testPublishDeniedForTreasurer(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::PUBLISH]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testPublishDeniedForActiveMember(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [GalleryImageVoter::PUBLISH]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }
}