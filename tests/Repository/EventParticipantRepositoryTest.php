<?php

namespace App\Tests\Repository;

use App\Entity\Event;
use App\Entity\EventParticipant;
use App\Entity\User;
use App\Enum\ParticipantRole;
use App\Repository\EventParticipantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventParticipantRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private EventParticipantRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->em = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->em->getRepository(EventParticipant::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Nettoyer les entités créées
        $this->em->close();
    }

    public function testUserParticipatesInEvent(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Test Location');
        
        $user = new User();
        $user->setPassword("test-hash-123");
        $user->setFirstName("Test");
        $user->setLastName("User");
        $user->setEmail('test@example.com');
        $user->setOfficeFunction(\App\Enum\OfficeFunction::ActiveMember);
        
        $this->em->persist($event);
        $this->em->persist($user);
        $this->em->flush();

        // Ajouter un participant
        $event->addParticipant($user, ParticipantRole::Participant);
        $this->em->flush();

        // Vérifier que l'utilisateur participe à l'événement
        $result = $this->repository->userParticipatesInEvent($user, $event);
        $this->assertTrue($result);

        // Créer un autre utilisateur qui ne participe pas
        $otherUser = new User();
        $otherUser->setPassword("test-hash-123");
        $otherUser->setFirstName("Test");
        $otherUser->setLastName("User");
        $otherUser->setEmail('other@example.com');
        $otherUser->setOfficeFunction(\App\Enum\OfficeFunction::ActiveMember);
        $this->em->persist($otherUser);
        $this->em->flush();

        $result = $this->repository->userParticipatesInEvent($otherUser, $event);
        $this->assertFalse($result);
    }

    public function testUserParticipatesIn(): void
    {
        $event1 = new Event();
        $event1->setTitle('Test Event 1');
        $event1->setDescription('Test Description 1');
        $event1->setStartDate(new \DateTime('2024-01-01'));
        $event1->setEndDate(new \DateTime('2024-01-02'));
        $event1->setLocation('Test Location 1');
        
        $event2 = new Event();
        $event2->setTitle('Test Event 2');
        $event2->setDescription('Test Description 2');
        $event2->setStartDate(new \DateTime('2024-02-01'));
        $event2->setEndDate(new \DateTime('2024-02-02'));
        $event2->setLocation('Test Location 2');
        
        $user = new User();
        $user->setPassword("test-hash-123");
        $user->setFirstName("Test");
        $user->setLastName("User");
        $user->setEmail('test@example.com');
        $user->setOfficeFunction(\App\Enum\OfficeFunction::ActiveMember);
        
        $this->em->persist($event1);
        $this->em->persist($event2);
        $this->em->persist($user);
        $this->em->flush();

        // Ajouter un participant à event1 uniquement
        $event1->addParticipant($user, ParticipantRole::Participant);
        $this->em->flush();

        // Vérifier avec un seul événement (celui où l'utilisateur participe)
        $result = $this->repository->userParticipatesIn($user, [$event1]);
        $this->assertTrue($result);

        // Vérifier avec un seul événement (celui où l'utilisateur ne participe pas)
        $result = $this->repository->userParticipatesIn($user, [$event2]);
        $this->assertFalse($result);

        // Vérifier avec plusieurs événements (dont un où l'utilisateur participe)
        $result = $this->repository->userParticipatesIn($user, [$event1, $event2]);
        $this->assertTrue($result);

        // Vérifier avec plusieurs événements (aucun où l'utilisateur participe)
        $event3 = new Event();
        $event3->setTitle('Test Event 3');
        $event3->setDescription('Test Description 3');
        $event3->setStartDate(new \DateTime('2024-03-01'));
        $event3->setEndDate(new \DateTime('2024-03-02'));
        $event3->setLocation('Test Location 3');
        $this->em->persist($event3);
        $this->em->flush();

        $result = $this->repository->userParticipatesIn($user, [$event2, $event3]);
        $this->assertFalse($result);

        // Vérifier avec un tableau vide
        $result = $this->repository->userParticipatesIn($user, []);
        $this->assertFalse($result);
    }

    public function testCountParticipants(): void
    {
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setDescription('Test Description');
        $event->setStartDate(new \DateTime('2024-01-01'));
        $event->setEndDate(new \DateTime('2024-01-02'));
        $event->setLocation('Test Location');
        
        $user1 = new User();
        $user1->setPassword("test-hash-123");
        $user1->setFirstName("Test");
        $user1->setLastName("User");
        $user1->setEmail('user1@example.com');
        $user1->setOfficeFunction(\App\Enum\OfficeFunction::ActiveMember);
        
        $user2 = new User();
        $user2->setPassword("test-hash-123");
        $user2->setFirstName("Test");
        $user2->setLastName("User");
        $user2->setEmail('user2@example.com');
        $user2->setOfficeFunction(\App\Enum\OfficeFunction::ActiveMember);
        
        $this->em->persist($event);
        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->flush();

        // Ajouter des participants
        $event->addParticipant($user1);
        $event->addParticipant($user2);
        $this->em->flush();

        $count = $this->repository->countParticipants($event);
        $this->assertSame(2, $count);
    }
}
