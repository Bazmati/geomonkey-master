<?php

namespace App\Tests\Service;

use App\Entity\MembershipFee;
use App\Repository\MembershipFeeRepository;
use App\Service\MembershipFeeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MembershipFeeServiceTest extends TestCase
{
    private MembershipFeeRepository $feeRepository;
    private EntityManagerInterface $em;
    private MembershipFeeService $service;

    protected function setUp(): void
    {
        $this->feeRepository = $this->createMock(MembershipFeeRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        
        $this->service = new MembershipFeeService(
            $this->feeRepository,
            $this->em
        );
    }

    public function testGetCurrentAmount(): void
    {
        $expectedAmount = 1500; // 15,00€ en centimes
        
        $this->feeRepository
            ->method('getCurrentAmount')
            ->willReturn($expectedAmount);
        
        $result = $this->service->getCurrentAmount();
        
        $this->assertEquals($expectedAmount, $result);
    }

    public function testGetCurrentAmountInEuros(): void
    {
        $amountInCents = 1500; // 15,00€
        $expectedAmountInEuros = 15.0;
        
        $this->feeRepository
            ->method('getCurrentAmount')
            ->willReturn($amountInCents);
        
        $result = $this->service->getCurrentAmountInEuros();
        
        $this->assertEquals($expectedAmountInEuros, $result);
        $this->assertIsFloat($result);
    }

    public function testGetCurrentAmountInEurosWithDifferentAmount(): void
    {
        $amountInCents = 2050; // 20,50€
        $expectedAmountInEuros = 20.5;
        
        $this->feeRepository
            ->method('getCurrentAmount')
            ->willReturn($amountInCents);
        
        $result = $this->service->getCurrentAmountInEuros();
        
        $this->assertEquals($expectedAmountInEuros, $result);
    }

    public function testGetCurrentFee(): void
    {
        $expectedFee = $this->createMock(MembershipFee::class);
        
        $this->feeRepository
            ->method('findCurrentFee')
            ->willReturn($expectedFee);
        
        $result = $this->service->getCurrentFee();
        
        $this->assertSame($expectedFee, $result);
    }

    public function testGetCurrentFeeThrowsExceptionWhenNoneFound(): void
    {
        $this->feeRepository
            ->method('findCurrentFee')
            ->willReturn(null);
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Aucun tarif d\'adhésion défini en base de données.');
        
        $this->service->getCurrentFee();
    }

    public function testGetCurrentAmountThrowsExceptionWhenNoneFound(): void
    {
        $this->feeRepository
            ->method('getCurrentAmount')
            ->willThrowException(new RuntimeException('Aucun tarif d\'adhésion défini en base de données.'));
        
        $this->expectException(RuntimeException::class);
        
        $this->service->getCurrentAmount();
    }

    public function testUpdateFee(): void
    {
        $amount = 2000; // 20,00€
        $effectiveFrom = new \DateTimeImmutable('2025-01-01');
        $notes = 'Tarif 2025 voté en AG';
        
        // Configurer le mock pour persister et flusher
        $this->em
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($fee) use ($amount, $effectiveFrom, $notes) {
                return $fee instanceof MembershipFee
                    && $fee->getAmount() === $amount
                    && $fee->getEffectiveFrom() === $effectiveFrom
                    && $fee->getNotes() === $notes;
            }));
        
        $this->em
            ->expects($this->once())
            ->method('flush');
        
        // Exécuter la méthode
        $result = $this->service->updateFee($amount, $effectiveFrom, $notes);
        
        // Vérifier que le résultat est une MembershipFee avec les bonnes valeurs
        $this->assertInstanceOf(MembershipFee::class, $result);
        $this->assertEquals($amount, $result->getAmount());
        $this->assertEquals($effectiveFrom, $result->getEffectiveFrom());
        $this->assertEquals($notes, $result->getNotes());
    }

    public function testUpdateFeeWithNullEffectiveFrom(): void
    {
        $amount = 1500;
        
        $this->em
            ->expects($this->once())
            ->method('persist');
        
        $this->em
            ->expects($this->once())
            ->method('flush');
        
        $result = $this->service->updateFee($amount);
        
        $this->assertInstanceOf(MembershipFee::class, $result);
        $this->assertEquals($amount, $result->getAmount());
        $this->assertNull($result->getEffectiveFrom());
        $this->assertNull($result->getNotes());
    }

    public function testHasFeeDefined(): void
    {
        $fee = $this->createMock(MembershipFee::class);
        
        $this->feeRepository
            ->method('findCurrentFee')
            ->willReturn($fee);
        
        $result = $this->service->hasFeeDefined();
        
        $this->assertTrue($result);
    }

    public function testHasFeeDefinedWhenNone(): void
    {
        $this->feeRepository
            ->method('findCurrentFee')
            ->willReturn(null);
        
        $result = $this->service->hasFeeDefined();
        
        $this->assertFalse($result);
    }

    public function testUpdateFeeCreatesNewInstanceEachTime(): void
    {
        $amount1 = 1500;
        $amount2 = 2000;
        
        $this->em
            ->expects($this->exactly(2))
            ->method('persist');
        
        $this->em
            ->expects($this->exactly(2))
            ->method('flush');
        
        $result1 = $this->service->updateFee($amount1);
        $result2 = $this->service->updateFee($amount2);
        
        // Les deux doivent être des instances différentes
        $this->assertNotSame($result1, $result2);
        $this->assertEquals($amount1, $result1->getAmount());
        $this->assertEquals($amount2, $result2->getAmount());
    }
}
