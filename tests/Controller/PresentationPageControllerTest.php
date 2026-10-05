<?php

namespace App\Tests\Controller;

use App\Entity\PresentationPage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PresentationPageControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    /** @var EntityRepository<PresentationPage> */
    private EntityRepository $presentationPageRepository;
    private string $path = '/presentation/page/';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get('doctrine')->getManager();
        $this->presentationPageRepository = $this->manager->getRepository(PresentationPage::class);

        foreach ($this->presentationPageRepository->findAll() as $object) {
            $this->manager->remove($object);
        }

        $this->manager->flush();
    }

    public function testIndex(): void
    {
        $this->client->followRedirects();
        $crawler = $this->client->request('GET', $this->path);

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('PresentationPage index');

        // Use the $crawler to perform additional assertions e.g.
        // self::assertSame('Some text on the page', $crawler->filter('.p')->first()->text());
    }

    public function testNew(): void
    {
        $this->client->request('GET', sprintf('%snew', $this->path));

        self::assertResponseStatusCodeSame(200);

        $this->client->submitForm('Save', [
            'presentation_page[title]' => 'Testing',
            'presentation_page[slug]' => 'Testing',
            'presentation_page[content]' => 'Testing',
            'presentation_page[position]' => 'Testing',
            'presentation_page[isActive]' => 'Testing',
            'presentation_page[updatedAt]' => 'Testing',
        ]);

        self::assertResponseRedirects('/presentation/page');

        self::assertSame(1, $this->presentationPageRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }

    public function testShow(): void
    {
        $fixture = new PresentationPage();
        $fixture->setTitle('My Title');
        $fixture->setSlug('My Title');
        $fixture->setContent('My Title');
        $fixture->setPosition('My Title');
        $fixture->setIsActive('My Title');
        $fixture->setUpdatedAt('My Title');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));

        self::assertResponseStatusCodeSame(200);
        self::assertPageTitleContains('PresentationPage');

        // Use assertions to check that the properties are properly displayed.
        $this->markTestIncomplete('This test was generated');
    }

    public function testEdit(): void
    {
        $fixture = new PresentationPage();
        $fixture->setTitle('Value');
        $fixture->setSlug('Value');
        $fixture->setContent('Value');
        $fixture->setPosition('Value');
        $fixture->setIsActive('Value');
        $fixture->setUpdatedAt('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s/edit', $this->path, $fixture->getId()));

        $this->client->submitForm('Update', [
            'presentation_page[title]' => 'Something New',
            'presentation_page[slug]' => 'Something New',
            'presentation_page[content]' => 'Something New',
            'presentation_page[position]' => 'Something New',
            'presentation_page[isActive]' => 'Something New',
            'presentation_page[updatedAt]' => 'Something New',
        ]);

        self::assertResponseRedirects('/presentation/page');

        $fixture = $this->presentationPageRepository->findAll();

        self::assertSame('Something New', $fixture[0]->getTitle());
        self::assertSame('Something New', $fixture[0]->getSlug());
        self::assertSame('Something New', $fixture[0]->getContent());
        self::assertSame('Something New', $fixture[0]->getPosition());
        self::assertSame('Something New', $fixture[0]->getIsActive());
        self::assertSame('Something New', $fixture[0]->getUpdatedAt());

        $this->markTestIncomplete('This test was generated');
    }

    public function testRemove(): void
    {
        $fixture = new PresentationPage();
        $fixture->setTitle('Value');
        $fixture->setSlug('Value');
        $fixture->setContent('Value');
        $fixture->setPosition('Value');
        $fixture->setIsActive('Value');
        $fixture->setUpdatedAt('Value');

        $this->manager->persist($fixture);
        $this->manager->flush();

        $this->client->request('GET', sprintf('%s%s', $this->path, $fixture->getId()));
        $this->client->submitForm('Delete');

        self::assertResponseRedirects('/presentation/page');
        self::assertSame(0, $this->presentationPageRepository->count([]));

        $this->markTestIncomplete('This test was generated');
    }
}
