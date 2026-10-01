<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use App\Enum\ParticipantRole;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testAddParticipant(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = $event->addParticipant($user);
        
        $this->assertCount(1, $event->getParticipants());
        $this->assertSame($user, $participant->getUser());
        $this->assertSame($event, $participant->getEvent());
        $this->assertSame(ParticipantRole::Participant, $participant->getRole());
    }

    public function testAddParticipantWithRole(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = $event->addParticipant($user, ParticipantRole::Organizer);
        
        $this->assertSame(ParticipantRole::Organizer, $participant->getRole());
        $this->assertTrue($participant->isOrganizer());
    }

    public function testAddParticipantDuplicate(): void
    {
        $event = new Event();
        $user = new User();
        
        // Ajouter le même utilisateur deux fois
        $firstParticipant = $event->addParticipant($user);
        $secondParticipant = $event->addParticipant($user);
        
        // Doit retourner le même participant
        $this->assertSame($firstParticipant, $secondParticipant);
        $this->assertCount(1, $event->getParticipants());
    }

    public function testAddParticipantDuplicateWithRoleUpdate(): void
    {
        $event = new Event();
        $user = new User();
        
        // Ajouter avec rôle participant
        $firstParticipant = $event->addParticipant($user, ParticipantRole::Participant);
        $this->assertSame(ParticipantRole::Participant, $firstParticipant->getRole());
        
        // Ajouter à nouveau avec rôle organisateur - doit mettre à jour le rôle
        $secondParticipant = $event->addParticipant($user, ParticipantRole::Organizer);
        
        // Doit retourner le même participant
        $this->assertSame($firstParticipant, $secondParticipant);
        $this->assertCount(1, $event->getParticipants());
        // Le rôle doit avoir été mis à jour
        $this->assertSame(ParticipantRole::Organizer, $secondParticipant->getRole());
    }

    public function testRemoveParticipant(): void
    {
        $event = new Event();
        $user = new User();
        
        $event->addParticipant($user);
        $this->assertCount(1, $event->getParticipants());
        
        $result = $event->removeParticipant($user);
        $this->assertTrue($result);
        $this->assertCount(0, $event->getParticipants());
    }

    public function testRemoveNonExistentParticipant(): void
    {
        $event = new Event();
        $user = new User();
        
        $result = $event->removeParticipant($user);
        $this->assertFalse($result);
    }

    public function testHasParticipant(): void
    {
        $event = new Event();
        $user1 = new User();
        $user2 = new User();
        
        $event->addParticipant($user1);
        
        $this->assertTrue($event->hasParticipant($user1));
        $this->assertFalse($event->hasParticipant($user2));
    }

    public function testIsUserOrganizer(): void
    {
        $event = new Event();
        $organizer = new User();
        $participant = new User();
        
        $event->addParticipant($organizer, ParticipantRole::Organizer);
        $event->addParticipant($participant, ParticipantRole::Participant);
        
        $this->assertTrue($event->isUserOrganizer($organizer));
        $this->assertFalse($event->isUserOrganizer($participant));
    }

    public function testParticipantCount(): void
    {
        $event = new Event();
        $user1 = new User();
        $user2 = new User();
        $user3 = new User();
        
        $event->addParticipant($user1);
        $event->addParticipant($user2);
        $event->addParticipant($user3);
        
        $this->assertSame(3, $event->getParticipantCount());
    }

    public function testGalleryImagesRelations(): void
    {
        $event = new Event();
        $galleryImage1 = new \App\Entity\GalleryImage();
        $galleryImage2 = new \App\Entity\GalleryImage();
        
        $event->addGalleryImage($galleryImage1);
        $event->addGalleryImage($galleryImage2);
        
        $this->assertCount(2, $event->getGalleryImages());
        $this->assertTrue($event->getGalleryImages()->contains($galleryImage1));
        $this->assertTrue($event->getGalleryImages()->contains($galleryImage2));
        
        $event->removeGalleryImage($galleryImage1);
        $this->assertCount(1, $event->getGalleryImages());
        $this->assertFalse($event->getGalleryImages()->contains($galleryImage1));
    }
}
