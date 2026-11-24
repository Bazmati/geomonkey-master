<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Module Messages (backoffice) — gouvernance ENTITY_MESSAGES :
 * le bureau étendu consulte la boîte mail de l'asso en lecture seule.
 *
 * En environnement de test, MAILBOX_* n'est pas défini : le service
 * rend « non configuré » et la page affiche l'alerte de configuration.
 * La connexion IMAP réelle (GMX) se vérifie à la main en local.
 */
final class MessageControllerTest extends WebTestCase
{
    private function createOfficeUser(OfficeFunction $function): User
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get('security.user_password_hasher');

        $user = (new User())->setEmail(sprintf('%s-messages-test@asso.fr', $function->value));
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

    public function testBureauSeesConfigurationAlertWhenMailboxNotConfigured(): void
    {
        $client = static::createClient();

        $client->loginUser($this->createOfficeUser(OfficeFunction::Treasurer));
        $client->request('GET', '/admin/message');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Messages');
        self::assertStringContainsString('non configurée', $client->getResponse()->getContent());
        self::assertStringContainsString('MAILBOX_HOST', $client->getResponse()->getContent());
    }

    public function testMemberWithoutOfficeFunctionIsDenied(): void
    {
        $client = static::createClient();

        $client->loginUser($this->createOfficeUser(OfficeFunction::None));
        $client->request('GET', '/admin/message');

        // SecurityExceptionListener : connecté avec fonction None -> /access-denied
        self::assertResponseRedirects('/access-denied');
    }

    public function testShowRedirectsToIndexWhenMailboxUnavailable(): void
    {
        $client = static::createClient();

        $client->loginUser($this->createOfficeUser(OfficeFunction::Secretary));
        $client->request('GET', '/admin/message/123');

        // Boîte non configurée : message injoignable -> retour propre à la liste
        self::assertResponseRedirects('/admin/message');
    }
}
