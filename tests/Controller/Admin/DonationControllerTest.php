<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Donation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Module Dons (backoffice) — gouvernance ENTITY_TREASURY :
 * le bureau étendu (trésorier inclus) voit les dons, un membre simple non.
 */
final class DonationControllerTest extends WebTestCase
{
    /**
     * Crée un utilisateur de bureau avec adhésion active.
     * RÔLE_ADMIN : exigé par le pare-feu pour tout /admin/* (couche 1,
     * appartenance au backoffice) ; le EntityVoter applique ensuite la
     * gouvernance fine (couche 2, prérogative de la fonction).
     */
    private function createOfficeUser(OfficeFunction $function): User
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get('security.user_password_hasher');

        $user = (new User())->setEmail(sprintf('%s-dons-test@asso.fr', $function->value));
        $user->setFirstName('Test');
        $user->setLastName($function->value);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $user->setRoles(['ROLE_ADMIN']);
        $user->setOfficeFunction($function);

        $membership = new Membership();
        $membership->setIsActive(true);
        $membership->setAmount(1500);
        $membership->setStartedAt((new \DateTimeImmutable())->sub(new \DateInterval('P1M')));
        $membership->setExpiresAt((new \DateTimeImmutable())->add(new \DateInterval('P11M')));
        $user->addMembership($membership);

        $em->persist($membership);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistDonation(string $status, int $amount, ?string $name): Donation
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $donation = new Donation();
        $donation->setAmount($amount);
        $donation->setDonorName($name);
        $donation->setDonorEmail($name ? strtolower(str_replace(' ', '.', $name)) . '@exemple.fr' : null);
        $donation->setMessage('Bravo pour le dôme !');
        $donation->setPaymentStatus($status);

        $em->persist($donation);
        $em->flush();

        return $donation;
    }

    public function testTreasurerCanAccessDonationIndex(): void
    {
        $client = static::createClient();

        $this->persistDonation(Donation::STATUS_PAID, 2500, 'Albert Donateur');
        $this->persistDonation(Donation::STATUS_PENDING, 1000, null);

        $client->loginUser($this->createOfficeUser(OfficeFunction::Treasurer));
        $client->request('GET', '/admin/donation');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dons');
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('Albert Donateur', $content);
        // 25,00 € affiché depuis les 2500 centimes
        self::assertStringContainsString('25,00', $content);
    }

    public function testFilterByStatusShowsOnlyPending(): void
    {
        $client = static::createClient();

        $this->persistDonation(Donation::STATUS_PAID, 2500, 'Albert Donateur');
        $this->persistDonation(Donation::STATUS_PENDING, 1000, 'Bernadette Essai');

        $client->loginUser($this->createOfficeUser(OfficeFunction::President));
        $client->request('GET', '/admin/donation', ['status' => 'pending']);

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertStringContainsString('Bernadette Essai', $content);
        self::assertStringNotContainsString('Albert Donateur', $content);
    }

    public function testMemberWithoutOfficeFunctionIsDenied(): void
    {
        $client = static::createClient();

        $client->loginUser($this->createOfficeUser(OfficeFunction::None));
        $client->request('GET', '/admin/donation');

        // SecurityExceptionListener : connecté avec fonction None -> /access-denied
        self::assertResponseRedirects('/access-denied');
    }

    /**
     * L'export CSV est un StreamedResponse qui écrit dans php://output :
     * le contenu n'est pas capturable par le client de test. On vérifie
     * donc la route, l'autorisation et les en-têtes HTTP du téléchargement.
     */
    public function testExportCsvStreamIsServedToTreasurer(): void
    {
        $client = static::createClient();

        $this->persistDonation(Donation::STATUS_PAID, 2500, 'Albert Donateur');

        $client->loginUser($this->createOfficeUser(OfficeFunction::Treasurer));
        $client->request('GET', '/admin/export/donations');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');

        $disposition = (string) $client->getResponse()->headers->get('Content-Disposition');
        self::assertStringContainsString('dons_', $disposition);
        self::assertStringContainsString('.csv', $disposition);
    }
}
