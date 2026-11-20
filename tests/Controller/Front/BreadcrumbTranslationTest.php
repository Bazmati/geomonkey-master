<?php

namespace App\Tests\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * i18n Lot 2c : fil d'Ariane bilingue.
 * BreadcrumbExtension traduit les libellés des routes front (nav.*)
 * via TranslatorInterface ; le backoffice reste FR.
 */
class BreadcrumbTranslationTest extends WebTestCase
{
    private function url(string $route): string
    {
        return static::getContainer()->get(RouterInterface::class)->generate($route);
    }

    public function testBreadcrumbIsFrenchByDefault(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->url('app_presentation_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav.gm-breadcrumb li a', 'Accueil');
        $this->assertSelectorTextContains('nav.gm-breadcrumb li.breadcrumb-item.active', 'Présentation');

        $client->request('GET', $this->url('app_event_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav.gm-breadcrumb li a', 'Accueil');
        $this->assertSelectorTextContains('nav.gm-breadcrumb li.breadcrumb-item.active', 'Événements');
    }

    public function testBreadcrumbIsEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_presentation_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav.gm-breadcrumb li a', 'Home');
        $this->assertSelectorTextContains('nav.gm-breadcrumb li.breadcrumb-item.active', 'About');

        $client->request('GET', $this->url('app_event_index'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav.gm-breadcrumb li a', 'Home');
        $this->assertSelectorTextContains('nav.gm-breadcrumb li.breadcrumb-item.active', 'Events');
    }
}
