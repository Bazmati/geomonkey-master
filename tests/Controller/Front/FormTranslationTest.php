<?php

namespace App\Tests\Controller\Front;

use App\Entity\MembershipFee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * i18n Lot 2a : pages formulaires (connexion, inscription, don).
 * Vérifie les textes figés du « hero » et les libellés statiques.
 * Depuis le Lot 2b, les libellés des champs Symfony (FormType) et les
 * messages de validation sont traduits : voir FormTypeTranslationTest.
 *
 * NB : /inscription redirige vers l'accueil si aucun tarif de
 * cotisation n'existe en base -> on sème un MembershipFee en setUp.
 * NB : les <h1> des héros sont coupés par <br>, le texte du crawler
 * colle les segments sans espace (« Content de terevoir ! »).
 */
class FormTranslationTest extends WebTestCase
{
    private function url(string $route): string
    {
        return static::getContainer()->get(RouterInterface::class)->generate($route);
    }

    private function seedMembershipFee(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $fee = new MembershipFee();
        $fee->setAmount(1500);
        $fee->setNotes('Tarif de test');
        $em->persist($fee);
        $em->flush();
    }

    public function testFormPagesAreFrenchByDefault(): void
    {
        $client = static::createClient();
        $this->seedMembershipFee();

        $client->request('GET', $this->url('app_login'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Content de terevoir !');
        $this->assertSelectorTextContains('form button[type=submit]', 'Se connecter');

        $client->request('GET', $this->url('app_membership_register'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Et si tu rejoignaisla bande ?');

        $client->request('GET', $this->url('app_donation'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Un petit geste,une grande aventure');
    }

    public function testFormPagesAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $this->seedMembershipFee();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_login'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'Welcomeback!');
        $this->assertSelectorTextContains('form button[type=submit]', 'Log in');

        $client->request('GET', $this->url('app_membership_register'));
        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertSelectorTextContains('h1.gm-join-title', 'What if you joinedthe gang?');
        $this->assertStringNotContainsString('register.title_a', $content);

        $client->request('GET', $this->url('app_donation'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1.gm-join-title', 'A small gesture,a great adventure');
        $this->assertSelectorTextContains('form button[type=submit]', 'Donate');
    }
}
