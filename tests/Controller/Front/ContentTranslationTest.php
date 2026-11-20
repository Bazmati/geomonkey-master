<?php

namespace App\Tests\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * i18n Lot 3 : partials et pages de contenu restantes.
 * Vérifie la sidebar événements (toujours rendue, même vide)
 * et le hero de la page Présentation. Le contenu DB (titres
 * d'articles, pages de présentation) n'est pas traduit.
 */
class ContentTranslationTest extends WebTestCase
{
    private function url(string $route): string
    {
        return static::getContainer()->get(RouterInterface::class)->generate($route);
    }

    public function testContentPagesAreFrenchByDefault(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->url('app_event_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.blog-sidebar', 'Prochains rendez-vous');
        $this->assertSelectorTextContains('.blog-sidebar', 'Derniers souvenirs');

        $client->request('GET', $this->url('app_presentation_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.pres-hero-title', 'Présentation de GéoMonkey');
        $this->assertSelectorTextContains('.home-hero-badge', 'Qui sommes-nous ?');
    }

    public function testContentPagesAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_event_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.blog-sidebar', 'Coming up next');
        $this->assertSelectorTextContains('.blog-sidebar', 'Recent memories');
        $this->assertStringNotContainsString('event.sidebar.next', $client->getResponse()->getContent());

        $client->request('GET', $this->url('app_presentation_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.pres-hero-title', 'About GéoMonkey');
        $this->assertSelectorTextContains('.home-hero-badge', 'Who are we?');
        $this->assertStringNotContainsString('presentation.hero_title', $client->getResponse()->getContent());
    }
}
