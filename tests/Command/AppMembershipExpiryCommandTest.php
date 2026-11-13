<?php

namespace App\Tests\Command;

use App\Command\AppMembershipExpiryCommand;
use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class AppMembershipExpiryCommandTest extends TestCase
{
    private MembershipRepository $membershipRepo;
    private UserRepository $userRepo;
    private EntityManagerInterface $em;
    private MailService $mailService;
    private AppMembershipExpiryCommand $command;

    protected function setUp(): void
    {
        $this->membershipRepo = $this->createMock(MembershipRepository::class);
        $this->userRepo = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mailService = $this->createMock(MailService::class);
        
        $this->command = new AppMembershipExpiryCommand(
            $this->membershipRepo,
            $this->userRepo,
            $this->em,
            $this->mailService
        );
    }

    public function testConfigure(): void
    {
        // Vérifier que la commande est correctement configurée
        $this->assertEquals('app:membership:expire', $this->command->getName());
        $this->assertIsString($this->command->getDescription());
    }

    public function testExecuteWithNoExpiringMembershipsAndNoUsersWithOfficeFunction(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        // Configurer les mocks
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->membershipRepo
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('getQuery')
            ->willReturn($query);
        
        $query
            ->method('getResult')
            ->willReturn([]);
        
        $this->userRepo
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
        
        $query
            ->method('getResult')
            ->willReturn([]);
        
        $result = $this->command->run($input, $output);
        
        $this->assertEquals(0, $result); // Command::SUCCESS
        $this->assertStringContainsString('No memberships expiring in the next 30 days', $output->fetch());
        $this->assertStringContainsString('No users with office function to check', $output->fetch());
    }

    public function testExecuteWithExpiringMemberships(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        // Créer des memberships qui expirent bientôt
        $membership1 = $this->createMock(Membership::class);
        $membership2 = $this->createMock(Membership::class);
        $expiringMemberships = [$membership1, $membership2];
        
        $user1 = $this->createMock(User::class);
        $user1->method('getEmail')->willReturn('user1@example.com');
        
        $user2 = $this->createMock(User::class);
        $user2->method('getEmail')->willReturn('user2@example.com');
        
        $expiresAt1 = new \DateTimeImmutable('+20 days');
        $expiresAt2 = new \DateTimeImmutable('+25 days');
        
        $membership1->method('getUser')->willReturn($user1);
        $membership1->method('getExpiresAt')->willReturn($expiresAt1);
        
        $membership2->method('getUser')->willReturn($user2);
        $membership2->method('getExpiresAt')->willReturn($expiresAt2);
        
        // Configurer les query builders
        $qbMembership = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMembership = $this->createMock(\Doctrine\ORM\Query::class);
        
        $qbUser = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryUser = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->membershipRepo
            ->method('createQueryBuilder')
            ->willReturn($qbMembership);
        
        $this->userRepo
            ->method('createQueryBuilder')
            ->willReturn($qbUser);
        
        $qbMembership
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbMembership
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbMembership
            ->method('getQuery')
            ->willReturn($queryMembership);
        
        $queryMembership
            ->method('getResult')
            ->willReturn($expiringMemberships);
        
        $qbUser
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbUser
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbUser
            ->method('getQuery')
            ->willReturn($queryUser);
        
        $queryUser
            ->method('getResult')
            ->willReturn([]);
        
        // Configurer le mail service
        $this->mailService
            ->expects($this->exactly(2))
            ->method('sendExpirationReminderEmail')
            ->withConsecutive(
                [$user1, $this->isType('int')],
                [$user2, $this->isType('int')]
            );
        
        // Configurer setReminderSentAt et flush
        $membership1
            ->expects($this->once())
            ->method('setReminderSentAt')
            ->with($this->isInstanceOf(\DateTimeImmutable::class));
        
        $membership2
            ->expects($this->once())
            ->method('setReminderSentAt')
            ->with($this->isInstanceOf(\DateTimeImmutable::class));
        
        $this->em
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->command->run($input, $output);
        
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Found 2 membership(s) expiring within 30 days', $output->fetch());
    }

    public function testExecuteWithExpiredMembershipsAndUsersToDowngrade(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        // Créer un utilisateur avec une fonction qui doit être rétrogradée
        $user1 = $this->createMock(User::class);
        $user1->method('getEmail')->willReturn('user1@example.com');
        $user1->method('getOfficeFunction')->willReturn(OfficeFunction::ActiveMember);
        
        $expiredMembership = $this->createMock(Membership::class);
        $expiredMembership->method('isActive')->willReturn(true);
        $expiredMembership->method('isExpired')->willReturn(true);
        
        $user1->method('getMemberships')->willReturn([$expiredMembership]);
        
        // Configurer les query builders
        $qbMembership = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMembership = $this->createMock(\Doctrine\ORM\Query::class);
        
        $qbUser = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryUser = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->membershipRepo
            ->method('createQueryBuilder')
            ->willReturn($qbMembership);
        
        $this->userRepo
            ->method('createQueryBuilder')
            ->willReturn($qbUser);
        
        $qbMembership
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbMembership
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbMembership
            ->method('getQuery')
            ->willReturn($queryMembership);
        
        $queryMembership
            ->method('getResult')
            ->willReturn([]);
        
        $qbUser
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbUser
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbUser
            ->method('getQuery')
            ->willReturn($queryUser);
        
        $queryUser
            ->method('getResult')
            ->willReturn([$user1]);
        
        // Configurer applyMembershipStatus
        $user1
            ->expects($this->once())
            ->method('applyMembershipStatus');
        
        $this->em
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->command->run($input, $output);
        
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Downgraded:', $output->fetch());
    }

    public function testExecuteWithMixedScenario(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        // Utilisateur 1: membership qui expire bientôt
        $user1 = $this->createMock(User::class);
        $user1->method('getEmail')->willReturn('user1@example.com');
        
        $membership1 = $this->createMock(Membership::class);
        $membership1->method('getUser')->willReturn($user1);
        $membership1->method('getExpiresAt')->willReturn(new \DateTimeImmutable('+15 days'));
        
        // Utilisateur 2: membership expiré + fonction à rétrograder
        $user2 = $this->createMock(User::class);
        $user2->method('getEmail')->willReturn('user2@example.com');
        $user2->method('getOfficeFunction')->willReturn(OfficeFunction::Treasurer);
        
        $membership2 = $this->createMock(Membership::class);
        $membership2->method('isActive')->willReturn(true);
        $membership2->method('isExpired')->willReturn(true);
        
        $user2->method('getMemberships')->willReturn([$membership2]);
        
        // Configurer les query builders
        $qbMembership = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMembership = $this->createMock(\Doctrine\ORM\Query::class);
        
        $qbUser = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryUser = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->membershipRepo
            ->method('createQueryBuilder')
            ->willReturn($qbMembership);
        
        $this->userRepo
            ->method('createQueryBuilder')
            ->willReturn($qbUser);
        
        $qbMembership
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbMembership
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbMembership
            ->method('getQuery')
            ->willReturn($queryMembership);
        
        $queryMembership
            ->method('getResult')
            ->willReturn([$membership1]);
        
        $qbUser
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbUser
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbUser
            ->method('getQuery')
            ->willReturn($queryUser);
        
        $queryUser
            ->method('getResult')
            ->willReturn([$user2]);
        
        // Configurer le mail service
        $this->mailService
            ->expects($this->once())
            ->method('sendExpirationReminderEmail')
            ->with($user1, $this->isType('int'));
        
        // Configurer setReminderSentAt
        $membership1
            ->expects($this->once())
            ->method('setReminderSentAt')
            ->with($this->isInstanceOf(\DateTimeImmutable::class));
        
        // Configurer applyMembershipStatus
        $user2
            ->expects($this->once())
            ->method('applyMembershipStatus');
        
        $this->em
            ->expects($this->exactly(2)) // flush après les rappels, flush après les rétrogradations
            ->method('flush');
        
        $result = $this->command->run($input, $output);
        
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Found 1 membership(s) expiring within 30 days', $output->fetch());
        $this->assertStringContainsString('Downgraded:', $output->fetch());
    }

    public function testExecuteWithExceptionInMailService(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        // Créer un membership qui expire bientôt
        $membership = $this->createMock(Membership::class);
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        
        $membership->method('getUser')->willReturn($user);
        $membership->method('getExpiresAt')->willReturn(new \DateTimeImmutable('+15 days'));
        
        $expiringMemberships = [$membership];
        
        // Configurer les query builders
        $qbMembership = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMembership = $this->createMock(\Doctrine\ORM\Query::class);
        
        $qbUser = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryUser = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->membershipRepo
            ->method('createQueryBuilder')
            ->willReturn($qbMembership);
        
        $this->userRepo
            ->method('createQueryBuilder')
            ->willReturn($qbUser);
        
        $qbMembership
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbMembership
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbMembership
            ->method('getQuery')
            ->willReturn($queryMembership);
        
        $queryMembership
            ->method('getResult')
            ->willReturn($expiringMemberships);
        
        $qbUser
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbUser
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbUser
            ->method('getQuery')
            ->willReturn($queryUser);
        
        $queryUser
            ->method('getResult')
            ->willReturn([]);
        
        // Configurer le mail service pour lancer une exception
        $this->mailService
            ->method('sendExpirationReminderEmail')
            ->willThrowException(new \Exception('Erreur d\'envoi d\'email'));
        
        $this->em
            ->expects($this->never())
            ->method('flush'); // Ne doit pas flusher si l'email échoue
        
        $result = $this->command->run($input, $output);
        
        // La commande doit continuer malgré l'erreur
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Error sending email', $output->fetch());
    }

    public function testExecuteWithNoUsersWithOfficeFunction(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        // Configurer les query builders
        $qbMembership = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMembership = $this->createMock(\Doctrine\ORM\Query::class);
        
        $qbUser = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryUser = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->membershipRepo
            ->method('createQueryBuilder')
            ->willReturn($qbMembership);
        
        $this->userRepo
            ->method('createQueryBuilder')
            ->willReturn($qbUser);
        
        $qbMembership
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbMembership
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbMembership
            ->method('getQuery')
            ->willReturn($queryMembership);
        
        $queryMembership
            ->method('getResult')
            ->willReturn([]);
        
        $qbUser
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbUser
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbUser
            ->method('getQuery')
            ->willReturn($queryUser);
        
        $queryUser
            ->method('getResult')
            ->willReturn([]);
        
        $result = $this->command->run($input, $output);
        
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('No users with office function to check', $output->fetch());
    }

    public function testExecuteWithUserHavingActiveMembership(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        
        // Créer un utilisateur avec une fonction et une membership active
        $user = $this->createMock(User::class);
        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('getOfficeFunction')->willReturn(OfficeFunction::Secretary);
        
        $activeMembership = $this->createMock(Membership::class);
        $activeMembership->method('isActive')->willReturn(true);
        $activeMembership->method('isExpired')->willReturn(false);
        
        $user->method('getMemberships')->willReturn([$activeMembership]);
        
        // Configurer les query builders
        $qbMembership = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMembership = $this->createMock(\Doctrine\ORM\Query::class);
        
        $qbUser = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryUser = $this->createMock(\Doctrine\ORM\Query::class);
        
        $this->membershipRepo
            ->method('createQueryBuilder')
            ->willReturn($qbMembership);
        
        $this->userRepo
            ->method('createQueryBuilder')
            ->willReturn($qbUser);
        
        $qbMembership
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbMembership
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbMembership
            ->method('getQuery')
            ->willReturn($queryMembership);
        
        $queryMembership
            ->method('getResult')
            ->willReturn([]);
        
        $qbUser
            ->method('andWhere')
            ->willReturnSelf();
        
        $qbUser
            ->method('setParameter')
            ->willReturnSelf();
        
        $qbUser
            ->method('getQuery')
            ->willReturn($queryUser);
        
        $queryUser
            ->method('getResult')
            ->willReturn([$user]);
        
        // applyMembershipStatus ne doit pas changer la fonction
        $user
            ->expects($this->never())
            ->method('applyMembershipStatus');
        
        $this->em
            ->expects($this->never())
            ->method('flush');
        
        $result = $this->command->run($input, $output);
        
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('No downgrades needed', $output->fetch());
    }
}
