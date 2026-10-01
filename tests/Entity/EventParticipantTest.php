<?php

namespace App\Tests\Entity;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use App\Enum\OfficeFunction;
use App\Enum\ParticipantRole;
use PHPUnit\Framework\TestCase;

class EventParticipantTest extends TestCase
{
    public function testCreateParticipant(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        $participant->setRole(ParticipantRole::Participant);
        
        $this->assertSame($event, $participant->getEvent());
        $this->assertSame($user, $participant->getUser());
        $this->assertSame(ParticipantRole::Participant, $participant->getRole());
        $this->assertInstanceOf(\DateTimeImmutable::class, $participant->getRegisteredAt());
        $this->assertFalse($participant->hasAttended());
        $this->assertNull($participant->getNotes());
    }

    public function testCreateOrganizerParticipant(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        $participant->setRole(ParticipantRole::Organizer);
        
        $this->assertTrue($participant->isOrganizer());
        $this->assertFalse($participant->isVolunteer());
        $this->assertFalse($participant->isParticipant());
    }

    public function testCreateVolunteerParticipant(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        $participant->setRole(ParticipantRole::Volunteer);
        
        $this->assertFalse($participant->isOrganizer());
        $this->assertTrue($participant->isVolunteer());
        $this->assertFalse($participant->isParticipant());
    }

    public function testSetAttended(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        
        $this->assertFalse($participant->hasAttended());
        
        $participant->setAttended(true);
        $this->assertTrue($participant->hasAttended());
    }

    public function testSetNotes(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        
        $this->assertNull($participant->getNotes());
        
        $participant->setNotes('Régime végétarien');
        $this->assertSame('Régime végétarien', $participant->getNotes());
    }

    public function testCompositeId(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        
        // Utiliser la réflexion pour accéder à l'ID de l'event et user
        $reflection = new \ReflectionClass($event);
        $property = $reflection->getProperty('id');
        $property->setValue($event, 123);
        
        $reflection = new \ReflectionClass($user);
        $property = $reflection->getProperty('id');
        $property->setValue($user, 456);
        
        $this->assertSame('123-456', $participant->getCompositeId());
    }

    public function testRegisteredAtIsNotNullable(): void
    {
        $event = new Event();
        $user = new User();
        
        $participant = new EventParticipant();
        $participant->setEvent($event);
        $participant->setUser($user);
        
        // registeredAt doit toujours avoir une valeur (définie dans le constructeur)
        $this->assertNotNull($participant->getRegisteredAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $participant->getRegisteredAt());
    }
}
