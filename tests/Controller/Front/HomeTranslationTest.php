<?php

namespace App\Tests\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * i18n Lot 1a : la homepage est entièrement bilingue.
 * Le contenu dynamique (titres d'événements, statuts, catégories,
 * extraits d'articles) vient de la base et n'est pas traduit :
 * on vérifie les textes figés de l'interface.
 */
class HomeTranslationTest extends WebTestCase
{
    public function testHomeIsFrenchByDefault(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('Créer des événements culturels', $content);
        $this->assertStringContainsString('Devenir membre', $content);
        // Pas de clé brute non traduite qui fuite dans le rendu
        $this->assertStringNotContainsString('home.hero.title_a', $content);
    }

    public function testHomeIsEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect(); // -> /

        $this->assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString('Creating cultural events', $content);
        $this->assertStringContainsString('Become a member', $content);
        $this->assertStringContainsString('Low-cost membership', $content);
        $this->assertStringNotContainsString('home.hero.title_a', $content);
    }
}
