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
        // On assert sur le texte du Crawler (et non sur le HTML brut)
        // car les apostrophes droites sont échappées en &#039; par Twig.
        $this->assertSelectorTextContains('h1.pres-hero-title', "La vie de l'assoc', jour après jour");

        $client->request('GET', $this->url('app_project_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.pres-hero-title', 'Des idées qui prennent vie');

        $client->request('GET', $this->url('app_gallery_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.pres-hero-title', 'Le patchwork des souvenirs');
    }

    public function testListingPagesAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_event_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.pres-hero-title', 'Asso life, day after day');
        $this->assertStringNotContainsString('event.hero.title', $client->getResponse()->getContent());

        $client->request('GET', $this->url('app_project_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.pres-hero-title', 'Ideas coming to life');

        $client->request('GET', $this->url('app_gallery_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.pres-hero-title', 'A patchwork of memories');
        $this->assertStringNotContainsString('gallery.hero.title', $client->getResponse()->getContent());
    }
}
