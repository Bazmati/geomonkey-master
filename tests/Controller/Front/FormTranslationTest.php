<?php

namespace App\Tests\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * i18n Lot 2a : pages formulaires (connexion, inscription, don).
 * Vérifie les textes figés du « hero » et les libellés statiques.
 * Les libellés des champs Symfony (FormType) et le contenu des
 * flashs restent en français pour l'instant (frontière acceptée).
 */
class FormTranslationTest extends WebTestCase
{
    private function url(string $route): string
    {
        return static::getContainer()->get(RouterInterface::class)->generate($route);
    }

    public function testFormPagesAreFrenchByDefault(): void
    {
        $client = static::createClient();

        $client->request('GET', $this->url('app_login'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Content de te revoir !');
        $this->assertSelectorTextContains('form button[type=submit]', 'Se connecter');

        $client->request('GET', $this->url('app_membership_register'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Et si tu rejoignais la bande ?');

        $client->request('GET', $this->url('app_donation'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Un petit geste, une grande aventure');
    }

    public function testFormPagesAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_login'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Welcome back!');
        $this->assertSelectorTextContains('form button[type=submit]', 'Log in');
        $this->assertSelectorTextContains('nav', 'Join'); // bouton navbar

        $client->request('GET', $this->url('app_membership_register'));
        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertSelectorTextContains('h1.gm-join-title', 'What if you joined the gang?');
        $this->assertStringNotContainsString('register.title_a', $content);

        $client->request('GET', $this->url('app_donation'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'A small gesture, a great adventure');
        $this->assertSelectorTextContains('form button[type=submit]', 'Donate');
    }
}
