<?php

namespace App\Tests\Integration;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\OfficeFunction;
use App\Enum\ParticipantRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Test d'intégration pour vérifier la gestion des doublons dans EventParticipant
 */
class EventParticipantDuplicateTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $kernel->getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testAddParticipantDuplicatePrevented(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Test Location');
        
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        
        $this->em->persist($event);
        $this->em->persist($user);
        $this->em->flush();

        // Ajouter le même utilisateur deux fois
        $firstParticipant = $event->addParticipant($user);
        $secondParticipant = $event->addParticipant($user);

        // Doit retourner le même participant
        $this->assertSame($firstParticipant, $secondParticipant);

        $this->em->flush();

        // Vérifier qu'il n'y a qu'un seul participant en base
        $participants = $event->getParticipants();
        $this->assertCount(1, $participants);

        // Vérifier dans le repository
        $repo = $this->em->getRepository(\App\Entity\EventParticipant::class);
        $count = $repo->countParticipants($event);
        $this->assertSame(1, $count);
    }

    public function testAddParticipantDuplicateWithRoleUpdate(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Test Location');
        
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        
        $this->em->persist($event);
        $this->em->persist($user);
        $this->em->flush();

        // Ajouter avec rôle participant
        $participant = $event->addParticipant($user, ParticipantRole::Participant);
        $this->assertSame(ParticipantRole::Participant, $participant->getRole());
        
        $this->em->flush();

        // Ajouter à nouveau avec rôle organisateur - doit mettre à jour le rôle
        $sameParticipant = $event->addParticipant($user, ParticipantRole::Organizer);
        
        // Doit retourner le même participant
        $this->assertSame($participant, $sameParticipant);

        // Le rôle doit avoir été mis à jour
        $this->assertSame(ParticipantRole::Organizer, $sameParticipant->getRole());

        $this->em->flush();

        // Vérifier qu'il n'y a toujours qu'un seul participant en base
        $participants = $event->getParticipants();
        $this->assertCount(1, $participants);

        // Vérifier que le rôle a été mis à jour en base
        $this->em->refresh($participant);
        $this->assertSame(ParticipantRole::Organizer, $participant->getRole());
    }

    public function testRemoveAndReAddParticipant(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Test Location');
        
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        
        $this->em->persist($event);
        $this->em->persist($user);
        $this->em->flush();

        // Ajouter puis supprimer
        $firstParticipant = $event->addParticipant($user);
        $firstId = $firstParticipant->getCompositeId();
        
        $this->em->flush();
        
        $event->removeParticipant($user);
        $this->em->flush();

        // Ajouter à nouveau
        $secondParticipant = $event->addParticipant($user);
        $secondId = $secondParticipant->getCompositeId();
        
        $this->em->flush();

        // Les IDs composites doivent être identiques (même utilisateur et événement)
        $this->assertSame($firstId, $secondId);

        // Mais les registeredAt doivent être différents (nouvelle date)
        $this->assertNotSame(
            $firstParticipant->getRegisteredAt(),
            $secondParticipant->getRegisteredAt()
        );

        // Vérifier qu'il n'y a qu'un seul participant en base
        $participants = $event->getParticipants();
        $this->assertCount(1, $participants);
    }

    public function testMultipleUsersSameEvent(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Test Location');
        
        $user1 = new User();
        $user1->setEmail('user1@example.com');
        $user1->setOfficeFunction(OfficeFunction::ActiveMember);
        
        $user2 = new User();
        $user2->setEmail('user2@example.com');
        $user2->setOfficeFunction(OfficeFunction::ActiveMember);
        
        $this->em->persist($event);
        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->flush();

        // Ajouter deux utilisateurs différents
        $participant1 = $event->addParticipant($user1);
        $participant2 = $event->addParticipant($user2);

        $this->assertNotSame($participant1, $participant2);

        $this->em->flush();

        // Vérifier qu'il y a deux participants en base
        $participants = $event->getParticipants();
        $this->assertCount(2, $participants);
    }
}
