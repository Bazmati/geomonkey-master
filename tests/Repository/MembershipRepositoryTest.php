<?php

namespace App\Tests\Repository;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use App\Repository\MembershipRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration des requêtes de MembershipRepository, sur la vraie BDD
 * de test (rollback automatique par DAMADoctrineTestBundle).
 */
class MembershipRepositoryTest extends KernelTestCase
{
    private MembershipRepository $repository;
    private \Doctrine\ORM\EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(MembershipRepository::class);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('Membership');
        $user->setPassword('not-a-real-hash');
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $this->em->persist($user);

        return $user;
    }

    private function createMembership(User $user, bool $isActive, \DateTimeImmutable $startedAt, \DateTimeImmutable $expiresAt, ?\DateTimeImmutable $reminderSentAt = null): Membership
    {
        $membership = new Membership();
        $membership->setUser($user);
        $membership->setIsActive($isActive);
        $membership->setStartedAt($startedAt);
        $membership->setExpiresAt($expiresAt);
        $membership->setAmount(1500); // colonne NOT NULL
        $membership->setReminderSentAt($reminderSentAt);
        $this->em->persist($membership);

        return $membership;
    }

    public function testFindLastActiveByUser(): void
    {
        $user = $this->createUser('find-last@test.fr');

        // La plus récente d'abord ; les inactives sont exclues
        $old = $this->createMembership($user, true, new \DateTimeImmutable('-3 years'), new \DateTimeImmutable('-2 years'));
        $new = $this->createMembership($user, true, new \DateTimeImmutable('-1 year'), new \DateTimeImmutable('+6 months'));
        $inactive = $this->createMembership($user, false, new \DateTimeImmutable('-2 years'), new \DateTimeImmutable('-1 year'));
        $this->em->flush();

        $result = $this->repository->findLastActiveByUser($user);

        $this->assertSame($new, $result, 'La dernière adhésion ACTIVE doit être retournée');
    }

    public function testFindLastActiveByUserOrdersByStartedAtDescending(): void
    {
        $user = $this->createUser('find-last-order@test.fr');

        $older = $this->createMembership($user, true, new \DateTimeImmutable('-2 years'), new \DateTimeImmutable('-1 year'));
        $newer = $this->createMembership($user, true, new \DateTimeImmutable('-1 year'), new \DateTimeImmutable('+1 month'));
        $this->em->flush();

        $this->assertSame($newer, $this->repository->findLastActiveByUser($user));
    }

    public function testFindLastActiveByUserLimitsToOneResult(): void
    {
        $user = $this->createUser('find-last-limit@test.fr');

        for ($i = 1; $i <= 3; $i++) {
            $this->createMembership($user, true, new \DateTimeImmutable('-' . ($i + 1) . ' years'), new \DateTimeImmutable('-' . $i . ' years'));
        }
        $this->em->flush();

        $this->assertNotNull($this->repository->findLastActiveByUser($user));
    }

    public function testFindLastActiveByUserWithNoMembership(): void
    {
        $user = $this->createUser('no-membership@test.fr');
        $this->em->flush();

        $this->assertNull($this->repository->findLastActiveByUser($user));
    }

    public function testHasActiveMembership(): void
    {
        $user = $this->createUser('has-active@test.fr');
        $this->createMembership($user, true, new \DateTimeImmutable('-1 month'), new \DateTimeImmutable('+11 months'));
        $this->em->flush();

        $this->assertTrue($this->repository->hasActiveMembership($user));
    }

    public function testHasActiveMembershipWithNoMembership(): void
    {
        $user = $this->createUser('has-active-none@test.fr');
        $this->em->flush();

        $this->assertFalse($this->repository->hasActiveMembership($user));
    }

    public function testHasActiveMembershipWithExpiredMembership(): void
    {
        $user = $this->createUser('has-active-expired@test.fr');
        $this->createMembership($user, true, new \DateTimeImmutable('-13 months'), new \DateTimeImmutable('-1 month'));
        $this->em->flush();

        $this->assertFalse($this->repository->hasActiveMembership($user));
    }

    public function testHasActiveMembershipWithInactiveMembership(): void
    {
        $user = $this->createUser('has-active-inactive@test.fr');
        $this->createMembership($user, false, new \DateTimeImmutable('-1 month'), new \DateTimeImmutable('+11 months'));
        $this->em->flush();

        $this->assertFalse($this->repository->hasActiveMembership($user));
    }

    public function testFindExpiredAndActive(): void
    {
        $user = $this->createUser('expired-active@test.fr');

        $expiredActive = $this->createMembership($user, true, new \DateTimeImmutable('-13 months'), new \DateTimeImmutable('-1 month'));
        $expiredInactive = $this->createMembership($user, false, new \DateTimeImmutable('-25 months'), new \DateTimeImmutable('-13 months'));
        $currentActive = $this->createMembership($user, true, new \DateTimeImmutable('-1 month'), new \DateTimeImmutable('+11 months'));
        $this->em->flush();

        $result = $this->repository->findExpiredAndActive();

        $this->assertContains($expiredActive, $result, 'Une adhésion expirée mais active doit être listée');
        $this->assertNotContains($expiredInactive, $result, 'Une adhésion inactive ne doit pas être listée');
        $this->assertNotContains($currentActive, $result, 'Une adhésion non expirée ne doit pas être listée');
    }

    public function testFindExpiredAndActiveFiltersCorrectly(): void
    {
        $user = $this->createUser('expired-filter@test.fr');

        $borderline = $this->createMembership($user, true, new \DateTimeImmutable('-2 hours'), new \DateTimeImmutable('-1 second'));
        $this->em->flush();

        $result = $this->repository->findExpiredAndActive();

        $this->assertNotContains($borderline, $result, 'expiresAt = maintenant pile : < now est strict');
    }

    public function testFindAboutToExpireWithoutReminder(): void
    {
        $user = $this->createUser('about-to-expire@test.fr');

        $in30Days = $this->createMembership($user, true, new \DateTimeImmutable('-11 months'), new \DateTimeImmutable('+30 days'));
        $in60Days = $this->createMembership($user, true, new \DateTimeImmutable('-10 months'), new \DateTimeImmutable('+60 days'));
        $alreadyExpired = $this->createMembership($user, true, new \DateTimeImmutable('-13 months'), new \DateTimeImmutable('-1 month'));
        $this->em->flush();

        $result = $this->repository->findAboutToExpireWithoutReminder();

        $this->assertContains($in30Days, $result, 'Expirant sous 30 jours sans rappel : listée');
        $this->assertNotContains($in60Days, $result, 'Expirant dans 60 jours : pas encore');
        $this->assertNotContains($alreadyExpired, $result, 'Déjà expirée : hors fenêtre');
    }

    public function testFindAboutToExpireIgnoresRecentlyReminded(): void
    {
        $user = $this->createUser('already-reminded@test.fr');

        $reminded = $this->createMembership(
            $user,
            true,
            new \DateTimeImmutable('-11 months'),
            new \DateTimeImmutable('+20 days'),
            new \DateTimeImmutable('-1 hour')
        );
        $remindedLongAgo = $this->createMembership(
            $user,
            true,
            new \DateTimeImmutable('-11 months'),
            new \DateTimeImmutable('+25 days'),
            new \DateTimeImmutable('-3 days')
        );
        $this->em->flush();

        $result = $this->repository->findAboutToExpireWithoutReminder();

        $this->assertNotContains($reminded, $result, 'Rappel envoyé récemment : pas de nouveau rappel');
        $this->assertContains($remindedLongAgo, $result, 'Rappel envoyé il y a plus de 24h : nouveau rappel possible');
    }
}
