<?php

namespace App\Tests\Repository;

use App\Entity\Donation;
use App\Repository\DonationRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration de DonationRepository sur la vraie BDD de test
 * (rollback automatique par DAMADoctrineTestBundle).
 */
class DonationRepositoryTest extends KernelTestCase
{
    private DonationRepository $repository;
    private \Doctrine\ORM\EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(DonationRepository::class);
    }

    protected function tearDown(): void
    {
        $this->em->close();
        parent::tearDown();
    }

    /**
     * createdAt est posé à "maintenant" par le constructeur : on le force
     * par réflexion pour tester le filtrage par année.
     */
    private function createDonation(int $amount, string $status, ?\DateTimeImmutable $createdAt): Donation
    {
        $donation = new Donation();
        $donation->setAmount($amount);
        $donation->setPaymentStatus($status);

        if (null !== $createdAt) {
            $property = new \ReflectionProperty(Donation::class, 'createdAt');
            $property->setValue($donation, $createdAt);
        }

        $this->em->persist($donation);

        return $donation;
    }

    public function testGetPaidTotalForYear(): void
    {
        $this->createDonation(1500, Donation::STATUS_PAID, new \DateTimeImmutable('2026-02-15 12:00:00'));
        $this->createDonation(2500, Donation::STATUS_PAID, new \DateTimeImmutable('2026-06-01 12:00:00'));
        $this->em->flush();

        $this->assertSame(4000, $this->repository->getPaidTotalForYear(2026));
    }

    public function testGetPaidTotalForYearIgnoresOtherStatusesAndYears(): void
    {
        $this->createDonation(1000, Donation::STATUS_PAID, new \DateTimeImmutable('2026-03-01 12:00:00'));
        $this->createDonation(9999, Donation::STATUS_PENDING, new \DateTimeImmutable('2026-03-02 12:00:00'));
        $this->createDonation(9999, Donation::STATUS_FAILED, new \DateTimeImmutable('2026-03-03 12:00:00'));
        $this->createDonation(9999, Donation::STATUS_PAID, new \DateTimeImmutable('2025-03-01 12:00:00'));
        $this->em->flush();

        $this->assertSame(1000, $this->repository->getPaidTotalForYear(2026));
    }

    public function testGetPaidTotalForYearWithNoDonations(): void
    {
        $this->assertSame(0, $this->repository->getPaidTotalForYear(2030));
    }

    public function testFindPaidForYear(): void
    {
        $oldest = $this->createDonation(1000, Donation::STATUS_PAID, new \DateTimeImmutable('2026-01-10 12:00:00'));
        $newest = $this->createDonation(2000, Donation::STATUS_PAID, new \DateTimeImmutable('2026-09-20 12:00:00'));
        $pending = $this->createDonation(3000, Donation::STATUS_PENDING, new \DateTimeImmutable('2026-05-05 12:00:00'));
        $otherYear = $this->createDonation(4000, Donation::STATUS_PAID, new \DateTimeImmutable('2024-05-05 12:00:00'));
        $this->em->flush();

        $result = $this->repository->findPaidForYear(2026);

        $this->assertCount(2, $result);
        $this->assertSame($newest, $result[0], 'Tri par date de création décroissante');
        $this->assertSame($oldest, $result[1]);
        $this->assertNotContains($pending, $result);
        $this->assertNotContains($otherYear, $result);
    }

    public function testFindPaidForYearWithNoDonations(): void
    {
        $this->assertSame([], $this->repository->findPaidForYear(2031));
    }
}
