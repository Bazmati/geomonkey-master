<?php

namespace App\Tests\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * i18n Lot 1c : pages légales.
 * Structure bilingue (hero, sommaire, titres, boutons) ;
 * le corps juridique reste en français (version faisant foi),
 * avec une mention explicite affichée uniquement en locale non-FR.
 */
class LegalTranslationTest extends WebTestCase
{
    private function url(string $route): string
    {
        return static::getContainer()->get(RouterInterface::class)->generate($route);
    }

    public function testLegalPagesAreFrenchByDefault(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->url('app_cgu'));
        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertSelectorTextContains('h1.gm-legal-title', 'Conditions Générales d\'Adhésion');
        $this->assertSelectorTextContains('.gm-legal-toc-title', 'Sommaire');
        $this->assertSelectorTextContains('#objet', 'Article 1 — Objet');
        // Pas de mention "version française fait foi" en FR
        $this->assertStringNotContainsString('legally binding', $content);
        $this->assertStringNotContainsString('cgu.a1', $content);

        $client->request('GET', $this->url('app_privacy'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-legal-title', 'Politique de Confidentialité');
        $this->assertStringNotContainsString('legally binding', $client->getResponse()->getContent());
    }

    public function testLegalPagesAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_cgu'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-legal-title', 'Membership Terms & Conditions');
        $this->assertSelectorTextContains('.gm-legal-toc-title', 'Contents');
        $this->assertSelectorTextContains('#objet', 'Article 1 — Purpose');
        // La mention "version française fait foi" est visible en EN
        $this->assertSelectorTextContains('[role=note]', 'legally binding');
        // Le corps juridique reste en français (version faisant foi)
        $this->assertStringContainsString('association loi 1901', $client->getResponse()->getContent());
        $this->assertStringNotContainsString('cgu.a1', $client->getResponse()->getContent());

        $client->request('GET', $this->url('app_privacy'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-legal-title', 'Privacy Policy');
        $this->assertSelectorTextContains('[role=note]', 'legally binding');
        $this->assertStringNotContainsString('privacy.a1', $client->getResponse()->getContent());
    }
}
