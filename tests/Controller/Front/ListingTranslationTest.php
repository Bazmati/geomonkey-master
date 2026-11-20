<?php

namespace App\Tests\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * i18n Lot 1b : les pages de listing sont bilingues.
 * Vérifie les textes figés du « hero » de chaque listing
 * (le contenu vient de la base et n'est pas traduit).
 */
class ListingTranslationTest extends WebTestCase
{
    private function url(string $route): string
    {
        return static::getContainer()->get(RouterInterface::class)->generate($route);
    }

    public function testListingPagesAreFrenchByDefault(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->url('app_event_index'));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('La vie de l\'assoc\', jour après jour', $client->getResponse()->getContent());

        $client->request('GET', $this->url('app_project_index'));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Des idées qui prennent vie', $client->getResponse()->getContent());

        $client->request('GET', $this->url('app_gallery_index'));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Le patchwork des souvenirs', $client->getResponse()->getContent());
    }

    public function testListingPagesAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_event_index'));
        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('Asso life, day after day', $content);
        $this->assertStringNotContainsString('event.hero.title', $content);

        $client->request('GET', $this->url('app_project_index'));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Ideas coming to life', $client->getResponse()->getContent());

        $client->request('GET', $this->url('app_gallery_index'));
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('A patchwork of memories', $client->getResponse()->getContent());
        $this->assertStringNotContainsString('gallery.hero.title', $client->getResponse()->getContent());
    }
}
