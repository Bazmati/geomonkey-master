<?php

namespace App\Tests\Controller\Front;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Page « Mon profil » (front) :
 * - un visiteur non connecté est renvoyé vers la connexion ;
 * - l'utilisateur connecté consulte son profil ;
 * - l'édition met à jour prénom/nom et horodate le consentement RGPD.
 */
class ProfileControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        foreach (['profil-show@test.fr', 'profil-edit@test.fr'] as $email) {
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($user !== null) {
                $this->em->remove($user);
            }
        }
        $this->em->flush();

        parent::tearDown();
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/profil');

        // Firewall : pas de profil public.
        $this->assertResponseRedirects('/login');
    }

    public function testShowDisplaysProfileData(): void
    {
        $user = $this->createUser('profil-show@test.fr');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profil');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mon profil');
        $this->assertSelectorTextContains('', 'Jean');
        $this->assertSelectorTextContains('', 'Profiltest');
        $this->assertSelectorTextContains('', 'profil-show@test.fr');
    }

    public function testEditUpdatesNameAndConsentTimestamp(): void
    {
        $user = $this->createUser('profil-edit@test.fr');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profil/modifier');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer')->form([
            'profile[firstName]' => 'Jeanne',
            'profile[lastName]' => 'Profilto',
            'profile[imageConsent]' => true,
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/profil');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('', 'mis à jour');

        $this->em->refresh($user);
        $this->assertSame('Jeanne', $user->getFirstName());
        $this->assertSame('Profilto', $user->getLastName());
        $this->assertTrue($user->getImageConsent());
        $this->assertNotNull($user->getImageConsentAt(), 'Le consentement photo doit être horodaté (RGPD)');
    }

    public function testEditRejectsEmptyFirstName(): void
    {
        $user = $this->createUser('profil-edit@test.fr');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profil/modifier');
        $form = $crawler->selectButton('Enregistrer')->form([
            'profile[firstName]' => '',
            'profile[lastName]' => 'Profiltest',
            'profile[imageConsent]' => false,
        ]);
        $this->client->submit($form);

        // Validation : refus, pas de redirection.
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('', 'obligatoire');

        $this->em->refresh($user);
        $this->assertSame('Jean', $user->getFirstName(), 'Le prénom ne doit pas être vidé en base');
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Jean');
        $user->setLastName('Profiltest');
        $user->setPassword(hash('crc32c', 'mot-de-passe-de-test')); // valeur jamais utilisée pour se connecter

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
