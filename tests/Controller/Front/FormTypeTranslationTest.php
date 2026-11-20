<?php

namespace App\Tests\Controller\Front;

use App\Entity\MembershipFee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

/**
 * i18n Lot 2b : libellés des FormType et messages de validation.
 * Testé via le formulaire de don (pas de captcha, pas d'auth)
 * et les libellés du formulaire d'inscription.
 *
 * NB : /inscription redirige vers l'accueil si aucun tarif de
 * cotisation n'existe en base -> on sème un MembershipFee.
 */
class FormTypeTranslationTest extends WebTestCase
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

    private function donationCsrfToken(Crawler $crawler): ?string
    {
        $input = $crawler->filter('form input[name="donation[_token]"]');
        if ($input->count() === 0) {
            return null;
        }

        return $input->attr('value');
    }

    public function testDonationFormLabelsAndValidationAreFrenchByDefault(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', $this->url('app_donation'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form', 'Montant de ton don (€)');
        $this->assertSelectorTextContains('form', 'Ton nom (optionnel)');
        $this->assertSelectorTextContains('form', 'Un petit mot pour l\'asso (optionnel)');

        // Validation : soumission d'un formulaire vide -> message traduit
        $client->request('POST', $this->url('app_donation'), [
            'donation' => ['_token' => $this->donationCsrfToken($crawler)],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form', 'Merci de choisir un montant.');
    }

    public function testDonationFormLabelsAndValidationAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $crawler = $client->request('GET', $this->url('app_donation'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form', 'Your donation amount (€)');
        $this->assertSelectorTextContains('form', 'Your name (optional)');
        $content = $client->getResponse()->getContent();
        $this->assertStringNotContainsString('form.amount_donation.label', $content);

        // Validation : soumission d'un formulaire vide -> message traduit
        $client->request('POST', $this->url('app_donation'), [
            'donation' => ['_token' => $this->donationCsrfToken($crawler)],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form', 'Please choose an amount.');
    }

    public function testRegistrationFormLabelsAreFrenchByDefault(): void
    {
        $client = static::createClient();
        $this->seedMembershipFee();

        $client->request('GET', $this->url('app_membership_register'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form', 'Adresse email');
        $this->assertSelectorTextContains('form', 'Mot de passe');
        $this->assertSelectorTextContains('form', 'Statut souhaité');
        $this->assertSelectorTextContains('form', 'Membre actif (accès complet, adhésion payante)');
        // label_html : les CGA et la politique de confidentialité restent cliquables
        $this->assertSelectorTextContains('form', 'Conditions Générales d\'Adhésion');
    }

    public function testRegistrationFormLabelsAreEnglishAfterSwitch(): void
    {
        $client = static::createClient();
        $this->seedMembershipFee();
        $client->request('GET', '/locale/en');
        $client->followRedirect();

        $client->request('GET', $this->url('app_membership_register'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('form', 'Email address');
        $this->assertSelectorTextContains('form', 'Password');
        $this->assertSelectorTextContains('form', 'Requested status');
        $this->assertSelectorTextContains('form', 'Active member (full access, paid membership)');
        $this->assertSelectorTextContains('form', 'Privacy Policy');
        $this->assertStringNotContainsString(
            'form.requested_function.label',
            $client->getResponse()->getContent()
        );
    }
}
