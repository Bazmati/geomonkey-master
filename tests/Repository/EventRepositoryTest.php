<?php

namespace App\Tests\Repository;

use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration d'EventRepository sur la vraie BDD de test
 * (rollback automatique par DAMADoctrineTestBundle).
 */
class EventRepositoryTest extends KernelTestCase
{
    private EventRepository $repository;
    private \Doctrine\ORM\EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(EventRepository::class);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    private function createEvent(string $title, string $when, bool $isPublished = true, bool $isMembersOnly = false): Event
    {
        $event = new Event();
        $event->setTitle($title);
        $event->setDescription('Description de ' . $title);
        $event->setStartDate(new \DateTime($when));
        $event->setEndDate((new \DateTime($when))->modify('+2 hours'));
        $event->setLocation('Lieu test');
        $event->setIsPublished($isPublished);
        $event->setIsMembersOnly($isMembersOnly);
        $this->em->persist($event);

        return $event;
    }

    public function testFindVisibleForActiveMemberAllEvents(): void
    {
        $upcoming = $this->createEvent('Événement à venir', '+2 days');
        $past = $this->createEvent('Événement passé', '-2 days');
        $this->em->flush();

        $result = $this->repository->findVisibleFor(true, 'tous');

        $this->assertContains($upcoming, $result);
        $this->assertContains($past, $result);
    }

    public function testFindVisibleForNonMemberHidesMembersOnly(): void
    {
        $public = $this->createEvent('Public', '+2 days');
        $membersOnly = $this->createEvent('Réservé membres', '+3 days', true, true);
        $this->em->flush();

        $nonMember = $this->repository->findVisibleFor(false, 'a-venir');
        $member = $this->repository->findVisibleFor(true, 'a-venir');

        $this->assertContains($public, $nonMember);
        $this->assertNotContains($membersOnly, $nonMember, 'Un non-membre ne doit pas voir les événements réservés aux membres');
        $this->assertContains($membersOnly, $member);
    }

    public function testFindVisibleForHidesUnpublished(): void
    {
        $published = $this->createEvent('Publié', '+2 days', true);
        $unpublished = $this->createEvent('Non publié', '+3 days', false);
        $this->em->flush();

        $result = $this->repository->findVisibleFor(false, 'a-venir');

        $this->assertContains($published, $result);
        $this->assertNotContains($unpublished, $result);
    }

    public function testFindVisibleForFilters(): void
    {
        $upcoming1 = $this->createEvent('À venir proche', '+2 days');
        $upcoming2 = $this->createEvent('À venir loin', '+5 days');
        $past1 = $this->createEvent('Passé récent', '-2 days');
        $past2 = $this->createEvent('Passé ancien', '-10 days');
        $this->em->flush();

        $upcoming = $this->repository->findVisibleFor(true, 'a-venir');
        $past = $this->repository->findVisibleFor(true, 'passes');
        $all = $this->repository->findVisibleFor(true, 'tous');

        $this->assertSame([$upcoming1, $upcoming2], array_values($upcoming), 'a-venir : du plus proche au plus lointain');
        $this->assertSame([$past1, $past2], array_values($past), 'passes : du plus récent au plus ancien');
        $this->assertSame([$upcoming1, $upcoming2, $past1, $past2], array_values($all), 'tous : à venir d\'abord, puis passés');
    }

    public function testFindUpcoming(): void
    {
        $upcoming = $this->createEvent('Futur', '+2 days');
        $this->createEvent('Ancien', '-2 days');
        $this->em->flush();

        $result = $this->repository->findUpcoming(5);

        $this->assertContains($upcoming, $result);
        $this->assertCount(1, $result, 'Les événements passés ne sont pas retournés');
    }

    public function testFindUpcomingRespectsLimit(): void
    {
        $this->createEvent('A', '+2 days');
        $this->createEvent('B', '+3 days');
        $this->createEvent('C', '+4 days');
        $this->em->flush();

        $this->assertCount(2, $this->repository->findUpcoming(2));
    }

    public function testFindOneVisibleBy(): void
    {
        $event = $this->createEvent('Visible', '+2 days');
        $this->em->flush();
        $id = $event->getId();

        $this->assertSame($event, $this->repository->findOneVisibleBy($id, false));
    }

    public function testFindOneVisibleByHidesUnpublished(): void
    {
        $event = $this->createEvent('Brouillon', '+2 days', false);
        $this->em->flush();

        $this->assertNull($this->repository->findOneVisibleBy($event->getId(), false), 'Un événement non publié ne doit jamais être servi, même avec l\'ID deviné');
    }

    public function testFindOneVisibleByMembersOnlyForNonMember(): void
    {
        $event = $this->createEvent('Réservé membres', '+2 days', true, true);
        $this->em->flush();

        $this->assertNull($this->repository->findOneVisibleBy($event->getId(), false));
        $this->assertSame($event, $this->repository->findOneVisibleBy($event->getId(), true));
    }

    public function testFindUpcomingVisibleFor(): void
    {
        $visible = $this->createEvent('Encore en cours', '+1 hour');
        $this->createEvent('Terminé', '-3 days');
        $membersOnly = $this->createEvent('Réservé membres', '+2 days', true, true);
        $this->em->flush();

        $nonMember = $this->repository->findUpcomingVisibleFor(false, 3);
        $member = $this->repository->findUpcomingVisibleFor(true, 3);

        $this->assertContains($visible, $nonMember);
        $this->assertNotContains($membersOnly, $nonMember);
        $this->assertContains($membersOnly, $member);
    }
}
