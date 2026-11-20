<?php

namespace App\Tests\Controller\Front;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * i18n Lot 0 : sélecteur de langue session/cookie, URLs inchangées.
 * - la page d'accueil est en français par défaut ;
 * - /locale/en passe l'interface en anglais (navbar) et pose le cookie ;
 * - une locale non supportée est rejetée (404).
 */
class LocaleControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDefaultInterfaceIsFrench(): void
    {
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#gm-nav', 'Accueil');
        $this->assertSelectorTextContains('#gm-nav', 'Newsletter');
        $this->assertStringContainsString(' Tous droits réservés', $this->client->getResponse()->getContent());
        // Le <html lang> suit la locale active
        $this->assertSame('fr', $crawler->filter('html')->attr('lang'));
    }

    public function testSwitchToEnglishTranslatesInterfaceAndSetsCookie(): void
    {
        // Sans referer : redirection vers l'accueil
        $this->client->request('GET', '/locale/en');
        $this->assertResponseRedirects('/');

        // Cookie longue durée posé
        $cookie = $this->client->getCookieJar()->get('gm_locale');
        $this->assertNotNull($cookie, 'Le cookie gm_locale doit être posé');
        $this->assertSame('en', $cookie->getValue());

        // La page suivante est en anglais
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#gm-nav', 'Home');
        $this->assertSelectorTextContains('#gm-nav', 'Events');
        $this->assertSame('en', $crawler->filter('html')->attr('lang'));
    }

    public function testUnsupportedLocaleIsRejected(): void
    {
        $this->client->request('GET', '/locale/de');

        $this->assertResponseStatusCodeSame(404);
    }
}
