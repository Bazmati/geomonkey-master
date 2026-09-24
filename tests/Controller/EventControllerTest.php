<?php

namespace App\Tests\Controller;

use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    /** @var EntityRepository<Event> */
    private EntityRepository $eventRepository;
    private string $path = '/event/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->eventRepository = $this->manager->getRepository(Event::class);

        foreach ($this->eventRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Event index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'event[title]' => 'Testing',
            'event[desciption]' => 'Testing',
            'event[startDate]' => 'Testing',
            'event[endDate]' => 'Testing',
            'event[location]' => 'Testing',
            'event[isPublished]' => 'Testing',
            'event[image]' => 'Testing',
        ]);

        self::assertResponseRedirects('/event');

        self::assertSame(1, $this->eventRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }

    public function testShow(): void
    {
        $fixture = new Event();
        $fixture->setTitle('My Title');
        $fixture->setDesciption('My Title');
        $fixture->setStartDate('My Title');
        $fixture->setEndDate('My Title');
        $fixture->setLocation('My Title');
        $fixture->setIsPublished('My Title');
        $fixture->setImage('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('Event');

        // Use assertions to check that the properties are properly displayed.
        $this->markTestIncomplete('This test was generated');
    }

    public function testEdit(): void
    {
        $fixture = new Event();
        $fixture->setTitle('Value');
        $fixture->setDesciption('Value');
        $fixture->setStartDate('Value');
        $fixture->setEndDate('Value');
        $fixture->setLocation('Value');
        $fixture->setIsPublished('Value');
        $fixture->setImage('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'event[title]' => 'Something New',
            'event[desciption]' => 'Something New',
            'event[startDate]' => 'Something New',
            'event[endDate]' => 'Something New',
            'event[location]' => 'Something New',
            'event[isPublished]' => 'Something New',
            'event[image]' => 'Something New',
        ]);

        self::assertResponseRedirects('/event');

        $fixture = $this->eventRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getTitle());
        self::assertSame('Something New', $fixture[0]->getDesciption());
        self::assertSame('Something New', $fixture[0]->getStartDate());
        self::assertSame('Something New', $fixture[0]->getEndDate());
        self::assertSame('Something New', $fixture[0]->getLocation());
        self::assertSame('Something New', $fixture[0]->getIsPublished());
        self::assertSame('Something New', $fixture[0]->getImage());

        $this->markTestIncomplete('This test was generated');
    }

    public function testRemove(): void
    {
        $fixture = new Event();
        $fixture->setTitle('Value');
        $fixture->setDesciption('Value');
        $fixture->setStartDate('Value');
        $fixture->setEndDate('Value');
        $fixture->setLocation('Value');
        $fixture->setIsPublished('Value');
        $fixture->setImage('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/event');
        self::assertSame(0, $this->eventRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }
}
