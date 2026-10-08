<?php

namespace App\Tests\Controller\Front;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationConsentTest extends WebTestCase
{
    public function testRegistrationTimestampsConsents(): void
    {
        $client = static::createClient();
        $this->seedMembershipFee();
        $crawler = $client->request('GET', '/inscription');
        $this->assertResponseIsSuccessful();

        $email = 'consent-test-' . time() . '@local.fr';
        $form = $crawler->filter('button[type="submit"]')->first()->form([
            'registration_form[email]' => $email,
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'Consent',
            'registration_form[plainPassword]' => 'password123',
            'registration_form[requestedFunction]' => 'membre_actif',
            'registration_form[agreeTerms]' => true,
            'registration_form[imageConsent]' => true,
        ]);
        $client->submit($form);

        // Redirection = inscription acceptée (attente-validation pour membre actif)
        $this->assertResponseRedirects('/inscription/attente-validation');

        // Vérification en base : consentements horodatés
        $user = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => $email]);
        $this->assertNotNull($user, 'L\'utilisateur doit exister en base');
        $this->assertNotNull($user->getTermsAcceptedAt(), 'termsAcceptedAt doit être horodaté');
        $this->assertTrue($user->getImageConsent(), 'imageConsent doit être true');
        $this->assertNotNull($user->getImageConsentAt(), 'imageConsentAt doit être horodaté');
    }

    public function testRegistrationRequiresTermsAcceptance(): void
    {
        $client = static::createClient();
        $this->seedMembershipFee();
        $crawler = $client->request('GET', '/inscription');

        $email = 'no-consent-' . time() . '@local.fr';
        $form = $crawler->filter('button[type="submit"]')->first()->form([
            'registration_form[email]' => $email,
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'NoConsent',
            'registration_form[plainPassword]' => 'password123',
            'registration_form[agreeTerms]' => false,   // case NON cochée
        ]);
        $client->submit($form);

        // Pas d'inscription sans consentement : pas de redirection
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.alert-danger, .text-danger', 'Un message d\'erreur doit s\'afficher');

        $user = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => $email]);
        $this->assertNull($user, 'Aucun utilisateur ne doit être créé sans acceptation des CGU');
    }

    public function testRegistrationWithImageConsentOnly(): void
    {
        $client = static::createClient();
        $this->seedMembershipFee();
        $crawler = $client->request('GET', '/inscription');

        $email = 'image-consent-' . time() . '@local.fr';
        $form = $crawler->filter('button[type="submit"]')->first()->form([
            'registration_form[email]' => $email,
            'registration_form[firstName]' => 'Test',
            'registration_form[lastName]' => 'ImageConsent',
            'registration_form[plainPassword]' => 'password123',
            'registration_form[requestedFunction]' => 'membre_actif',
            'registration_form[agreeTerms]' => true,
            'registration_form[imageConsent]' => false,  // Refus consentement image
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/inscription/attente-validation');

        $user = static::getContainer()->get(UserRepository::class)
            ->findOneBy(['email' => $email]);
        $this->assertNotNull($user);
        $this->assertFalse($user->getImageConsent(), 'imageConsent doit être false');
        $this->assertNull($user->getImageConsentAt(), 'imageConsentAt doit être null quand consentement refusé');
    }

    private function seedMembershipFee(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $fee = new \App\Entity\MembershipFee();
        $fee->setAmount(1500); // 15 € — updatedAt est posé par le constructeur, effectiveFrom peut rester null
        $em->persist($fee);
        $em->flush();
    }
}