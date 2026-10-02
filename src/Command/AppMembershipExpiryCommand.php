<?php

namespace App\Command;

use App\Entity\Membership;
use App\Enum\OfficeFunction;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\MailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AppMembershipExpiryCommand extends Command
{
    private $membershipRepo;
    private $userRepo;
    private $em;
    private $mailService;

    public function __construct(
        MembershipRepository $membershipRepo,
        UserRepository $userRepo,
        EntityManagerInterface $em,
        MailService $mailService
    ) {
        $this->membershipRepo = $membershipRepo;
        $this->userRepo = $userRepo;
        $this->em = $em;
        $this->mailService = $mailService;
        
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('app:membership:expire')
            ->setDescription('Check and manage membership expirations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Membership Expiry Management');

        $now = new \DateTimeImmutable();
        $thirtyDaysLater = $now->add(new \DateInterval('P30D'));

        $io->section('Sending expiration reminders');
        $this->sendExpirationReminders($now, $thirtyDaysLater, $io);

        $io->section('Downgrading expired members');
        $this->expireMemberships($now, $io);

        $io->success('Processing completed.');
        return Command::SUCCESS;
    }

    private function sendExpirationReminders(\DateTimeImmutable $now, \DateTimeImmutable $thirtyDaysLater, SymfonyStyle $io): void
    {
        $expiringSoon = $this->membershipRepo->createQueryBuilder('m')
            ->andWhere('m.expiresAt BETWEEN :now AND :thirtyDaysLater')
            ->andWhere('m.isActive = :active')
            ->andWhere('m.reminderSentAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('thirtyDaysLater', $thirtyDaysLater)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        if (empty($expiringSoon)) {
            $io->text('No memberships expiring in the next 30 days.');
            return;
        }

        $io->text(sprintf('Found %d membership(s) expiring within 30 days.', count($expiringSoon)));

        foreach ($expiringSoon as $membership) {
            $user = $membership->getUser();
            if (!$user) {
                continue;
            }

            $expiresAt = $membership->getExpiresAt();
            $daysUntilExpiry = $expiresAt ? $now->diff($expiresAt)->days : 0;

            try {
                $this->mailService->sendExpirationReminderEmail($user, $daysUntilExpiry);
                $membership->setReminderSentAt($now);
                $io->text(sprintf('Reminder sent to %s (expires in %d days)', $user->getEmail(), $daysUntilExpiry));
            } catch (\Exception $e) {
                $io->error(sprintf('Error sending email to %s: %s', $user->getEmail(), $e->getMessage()));
            }
        }

        $this->em->flush();
    }

    private function expireMemberships(\DateTimeImmutable $now, SymfonyStyle $io): void
    {
        $activeOfficeUsers = $this->userRepo->createQueryBuilder('u')
            ->andWhere('u.officeFunction != :none')
            ->setParameter('none', OfficeFunction::None->value)
            ->getQuery()
            ->getResult();

        if (empty($activeOfficeUsers)) {
            $io->text('No users with office function to check.');
            return;
        }

        $io->text(sprintf('Checking %d user(s) with office function.', count($activeOfficeUsers)));

        $downgradedCount = 0;
        
        foreach ($activeOfficeUsers as $user) {
            $hasActive = false;
            foreach ($user->getMemberships() as $membership) {
                if ($membership->isActive() && !$membership->isExpired()) {
                    $hasActive = true;
                    break;
                }
            }

            if (!$hasActive) {
                $oldFunction = $user->getOfficeFunction();
                $user->applyMembershipStatus();
                $io->text(sprintf('Downgraded: %s from %s -> None', $user->getEmail(), $oldFunction->value));
                $downgradedCount++;
            }
        }

        if ($downgradedCount > 0) {
            $io->text(sprintf('Total: %d user(s) downgraded.', $downgradedCount));
        } else {
            $io->text('No downgrades needed.');
        }

        $this->em->flush();
    }
}
