<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Encaissement hors-ligne d'une cotisation (espèces, chèque, virement) :
 * réplication manuelle de la logique post-paiement du webhook Stripe.
 */
final class MembershipOfflinePaymentTest extends WebTestCase
{
    /** Crée un président (avec adhésion active : le EntityVoter l'exige) et un futur membre. */
    private function createPresidentAndMember(): array
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get('security.user_password_hasher');

        $president = (new User())->setEmail('president-offline@asso.fr');
        $president->setFirstName('Président');
        $president->setLastName('Test');
        $president->setPassword($hasher->hashPassword($president, 'password'));
        $president->setRoles(['ROLE_ADMIN']);
        $president->setOfficeFunction(OfficeFunction::President);

        $membership = new Membership();
        $membership->setIsActive(true);
        $membership->setAmount(1500);
        $membership->setStartedAt(new \DateTimeImmutable());
        $membership->setExpiresAt((new \DateTimeImmutable())->add(new \DateInterval('P1Y')));
        $president->addMembership($membership);
        $membership->setPaymentStatus('paid');
        $em->persist($membership);

        $member = (new User())->setEmail('membre-offline@asso.fr');
        $member->setFirstName('Marcel');
        $member->setLastName('Monkey');
        $member->setPassword($hasher->hashPassword($member, 'password'));
        $member->setOfficeFunction(OfficeFunction::None);

        $em->persist($president);
        $em->persist($member);
        $em->flush();

        return [$president, $member];
    }

    /**
     * Crée un utilisateur de bureau avec une adhésion active
     * (le EntityVoter l'exige pour toute fonction de bureau).
     */
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
        $user->addMembership($membership);
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

    public function testOfflinePageRendersWithMembers(): void
    {
        $client = static::createClient();
        [$president, $member] = $this->createPresidentAndMember();

        $client->loginUser($president);
        $crawler = $client->request('GET', '/admin/membership/hors-ligne');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Encaisser une cotisation hors-ligne');
        self::assertSelectorTextContains('form select#user_id', 'Monkey Marcel');
    }

    public function testCashPaymentActivatesMembershipAndMemberStatus(): void
    {
        $client = static::createClient();
        [$president, $member] = $this->createPresidentAndMember();

        $client->loginUser($president);
        $crawler = $client->request('GET', '/admin/membership/hors-ligne');

        $client->request('POST', '/admin/membership/hors-ligne', [
            '_token' => $this->csrfToken($crawler),
            'user_id' => (string) $member->getId(),
            'amount' => '15',
            'method' => 'cheque',
        ]);
        self::assertResponseRedirects('/admin/membership/list');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'encaissée');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        /** @var User $memberReloaded */
        $memberReloaded = $em->getRepository(User::class)->find($member->getId());

        // Le statut du membre suit le paiement (règle du webhook)
        self::assertSame(OfficeFunction::ActiveMember, $memberReloaded->getOfficeFunction());
        self::assertTrue($memberReloaded->isRegistrationConfirmed());
        self::assertNotNull($memberReloaded->getMembershipValidUntil());

        // L'adhésion créée : payée, active, valable 1 an, traçable hors-ligne
        $active = $memberReloaded->getActiveMembership();
        self::assertNotNull($active);
        self::assertSame('paid', $active->getPaymentStatus());
        self::assertSame(1500, $active->getAmount());
        self::assertStringStartsWith('MANUEL-cheque-', (string) $active->getStripePaymentId());
        self::assertTrue($active->getExpiresAt() > new \DateTimeImmutable('+11 months'));
    }

    public function testInvalidMethodIsRejected(): void
    {
        $client = static::createClient();
        [$president, $member] = $this->createPresidentAndMember();

        $client->loginUser($president);
        $crawler = $client->request('GET', '/admin/membership/hors-ligne');

        $client->request('POST', '/admin/membership/hors-ligne', [
            '_token' => $this->csrfToken($crawler),
            'user_id' => (string) $member->getId(),
            'amount' => '15',
            'method' => 'paypal',
        ]);
        self::assertResponseRedirects('/admin/membership/hors-ligne');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        /** @var User $memberReloaded */
        $memberReloaded = $em->getRepository(User::class)->find($member->getId());

        self::assertSame(OfficeFunction::None, $memberReloaded->getOfficeFunction());
        self::assertNull($memberReloaded->getActiveMembership());
    }

    public function testTreasurerCanAccessOfflinePage(): void
    {
        $client = static::createClient();
        [$president, $member] = $this->createPresidentAndMember();
        $treasurer = $this->createOfficeUser('tresorier-offline@asso.fr', OfficeFunction::Treasurer);

        $client->loginUser($treasurer);
        $crawler = $client->request('GET', '/admin/membership/hors-ligne');

        // Le trésorier encaisse : accès direct à la page
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Encaisser une cotisation hors-ligne');
    }

    public function testSecretaryIsForbiddenFromOfflinePage(): void
    {
        $client = static::createClient();
        [$president, $member] = $this->createPresidentAndMember();
        $secretary = $this->createOfficeUser('secretaire-offline@asso.fr', OfficeFunction::Secretary);

        $client->loginUser($secretary);
        $client->request('GET', '/admin/membership/hors-ligne');

        // Le secrétaire passe l'ENTITY_EDIT de la classe mais pas l'ENTITY_CASH
        self::assertResponseStatusCodeSame(403);
    }
}
