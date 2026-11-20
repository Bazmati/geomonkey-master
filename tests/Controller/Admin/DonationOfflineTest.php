<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Donation;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Encaissement hors-ligne d'un don (espèces, chèque, virement) :
 * don récolté en main propre, enregistré payé sans frais (net = brut),
 * traçable via un stripe_session_id préfixé MANUEL-.
 */
final class DonationOfflineTest extends WebTestCase
{

    private const ROUTE = '/admin/donation/hors-ligne';
    private const PAGE_H1 = 'Encaisser un don hors-ligne';

    /** Crée un utilisateur de bureau avec une adhésion active (voter requis). */
    private function createOfficeUser(string $email, OfficeFunction $function): User
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get('security.user_password_hasher');

        $user = (new User())->setEmail($email);
        $user->setFirstName('Bureau');
        $user->setLastName('Test');
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $user->setRoles(['ROLE_ADMIN']);
        $user->setOfficeFunction($function);

        $membership = new Membership();
        $membership->setIsActive(true);
        $membership->setAmount(1500);
        $membership->setStartedAt(new \DateTimeImmutable());
        $membership->setExpiresAt((new \DateTimeImmutable())->add(new \DateInterval('P1Y')));
        $membership->setUser($user);
        $membership->setPaymentStatus('paid');
        $em->persist($membership);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function csrfToken(Crawler $crawler): ?string
    {
        $input = $crawler->filter('input[name="_token"]');
        if (0 === $input->count()) {
            return null;
        }

        return $input->attr('value');
    }

    private function countDonations(): int
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        return (int) $em->getRepository(Donation::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function testOfflinePageRendersForTreasurer(): void
    {
        $client = static::createClient();
        $treasurer = $this->createOfficeUser('tresorier-don@asso.fr', OfficeFunction::Treasurer);

        $client->loginUser($treasurer);
        $crawler = $client->request('GET', self::ROUTE);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', self::PAGE_H1);
        self::assertSelectorExists('form select#method');
    }

    public function testCashDonationIsRecordedAsPaidWithTraceability(): void
    {
        $client = static::createClient();
        $treasurer = $this->createOfficeUser('tresorier-don2@asso.fr', OfficeFunction::Treasurer);

        $client->loginUser($treasurer);
        $crawler = $client->request('GET', self::ROUTE);

        $before = $this->countDonations();

        $client->request('POST', self::ROUTE, [
            '_token' => $this->csrfToken($crawler),
            'amount' => '20.50',
            'method' => 'especes',
            'donor_name' => 'Jean Philantrope',
            'donor_email' => 'jean@exemple.fr',
            'message' => 'Don collecté lors de la sortie du 14 juillet',
        ]);

        self::assertResponseRedirects('/admin/donation');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'encaissé');

        // Un seul nouveau don, avec toutes les propriétés attendues
        self::assertSame($before + 1, $this->countDonations());

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();

        /** @var Donation $donation */
        $donation = $em->getRepository(Donation::class)->findOneBy(
            ['donorEmail' => 'jean@exemple.fr'],
            ['id' => 'DESC']
        );

        self::assertNotNull($donation);
        self::assertSame(2050, $donation->getAmount());
        self::assertSame(Donation::STATUS_PAID, $donation->getPaymentStatus());
        // Encaissement en main propre : aucun frais, net = brut
        self::assertSame(0, $donation->getFeeAmount());
        self::assertSame(2050, $donation->getNetAmount());
        self::assertSame('Jean Philantrope', $donation->getDonorName());
        self::assertStringStartsWith('MANUEL-especes-', (string) $donation->getStripeSessionId());
    }

    public function testInvalidMethodIsRejected(): void
    {
        $client = static::createClient();
        $treasurer = $this->createOfficeUser('tresorier-don3@asso.fr', OfficeFunction::Treasurer);

        $client->loginUser($treasurer);
        $crawler = $client->request('GET', self::ROUTE);

        $before = $this->countDonations();

        $client->request('POST', self::ROUTE, [
            '_token' => $this->csrfToken($crawler),
            'amount' => '20',
            'method' => 'paypal',
        ]);

        // Retour au formulaire, rien n'est enregistré
        self::assertResponseRedirects(self::ROUTE);
        self::assertSame($before, $this->countDonations());
    }

    public function testSecretaryIsForbiddenFromOfflineDonation(): void
    {
        $client = static::createClient();
        $secretary = $this->createOfficeUser('secretaire-don@asso.fr', OfficeFunction::Secretary);

        $client->loginUser($secretary);
        $client->request('GET', self::ROUTE);

        // Le secrétaire passe l'ENTITY_TREASURY de la classe mais pas l'ENTITY_CASH
        self::assertResponseStatusCodeSame(403);
    }
}
