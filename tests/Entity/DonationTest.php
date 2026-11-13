<?php

namespace App\Tests\Entity;

use App\Entity\Donation;
use PHPUnit\Framework\TestCase;

class DonationTest extends TestCase
{
    public function testDonationCreation(): void
    {
        $donation = new Donation();
        
        $this->assertNull($donation->getId());
        $this->assertEquals(Donation::STATUS_PENDING, $donation->getPaymentStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $donation->getCreatedAt());
        // amount n'a pas de valeur par défaut et n'est pas nullable, donc on ne peut pas l'appeler avant de le setter
        $this->assertNull($donation->getDonorName());
        $this->assertNull($donation->getDonorEmail());
        $this->assertNull($donation->getMessage());
        $this->assertNull($donation->getStripeSessionId());
    }

    public function testGetAmountInEuros(): void
    {
        $donation = new Donation();
        $donation->setAmount(2000); // 20,00€ en centimes
        
        $this->assertEquals(20.0, $donation->getAmountInEuros());
        $this->assertIsFloat($donation->getAmountInEuros());
    }

    public function testGetAmountInEurosWithDifferentValues(): void
    {
        $donation = new Donation();
        
        $donation->setAmount(500); // 5,00€
        $this->assertEquals(5.0, $donation->getAmountInEuros());
        
        $donation->setAmount(999); // 9,99€
        $this->assertEquals(9.99, $donation->getAmountInEuros());
        
        $donation->setAmount(10000); // 100,00€
        $this->assertEquals(100.0, $donation->getAmountInEuros());
    }

    public function testPaymentStatusConstants(): void
    {
        $this->assertEquals('pending', Donation::STATUS_PENDING);
        $this->assertEquals('paid', Donation::STATUS_PAID);
        $this->assertEquals('failed', Donation::STATUS_FAILED);
    }

    public function testSetAndGetAmount(): void
    {
        $donation = new Donation();
        
        $donation->setAmount(5000);
        $this->assertEquals(5000, $donation->getAmount());
        
        $donation->setAmount(10000);
        $this->assertEquals(10000, $donation->getAmount());
    }

    public function testSetAndGetDonorName(): void
    {
        $donation = new Donation();
        
        $donation->setDonorName('John Doe');
        $this->assertEquals('John Doe', $donation->getDonorName());
        
        $donation->setDonorName('Jane Smith');
        $this->assertEquals('Jane Smith', $donation->getDonorName());
    }

    public function testSetAndGetDonorNameWithNull(): void
    {
        $donation = new Donation();
        
        $donation->setDonorName(null);
        $this->assertNull($donation->getDonorName());
    }

    public function testSetAndGetDonorEmail(): void
    {
        $donation = new Donation();
        
        $donation->setDonorEmail('john@example.com');
        $this->assertEquals('john@example.com', $donation->getDonorEmail());
        
        $donation->setDonorEmail('jane@example.com');
        $this->assertEquals('jane@example.com', $donation->getDonorEmail());
    }

    public function testSetAndGetDonorEmailWithNull(): void
    {
        $donation = new Donation();
        
        $donation->setDonorEmail(null);
        $this->assertNull($donation->getDonorEmail());
    }

    public function testSetAndGetMessage(): void
    {
        $donation = new Donation();
        
        $message = 'Un message pour soutenir l\'association';
        $donation->setMessage($message);
        $this->assertEquals($message, $donation->getMessage());
        
        $donation->setMessage(null);
        $this->assertNull($donation->getMessage());
    }

    public function testSetAndGetStripeSessionId(): void
    {
        $donation = new Donation();
        
        $sessionId = 'cs_test_123456789';
        $donation->setStripeSessionId($sessionId);
        $this->assertEquals($sessionId, $donation->getStripeSessionId());
        
        $donation->setStripeSessionId(null);
        $this->assertNull($donation->getStripeSessionId());
    }

    public function testSetAndGetPaymentStatus(): void
    {
        $donation = new Donation();
        
        // Par défaut
        $this->assertEquals(Donation::STATUS_PENDING, $donation->getPaymentStatus());
        
        // Changer le statut
        $donation->setPaymentStatus(Donation::STATUS_PAID);
        $this->assertEquals(Donation::STATUS_PAID, $donation->getPaymentStatus());
        
        $donation->setPaymentStatus(Donation::STATUS_FAILED);
        $this->assertEquals(Donation::STATUS_FAILED, $donation->getPaymentStatus());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $donation = new Donation();
        $customDate = new \DateTimeImmutable('2024-01-15 10:30:00');
        
        $donation->setCreatedAt($customDate);
        $this->assertEquals($customDate, $donation->getCreatedAt());
    }

    public function testDonationWithAllFields(): void
    {
        $donation = new Donation();
        $donation
            ->setAmount(2500) // 25,00€
            ->setDonorName('Jean Dupont')
            ->setDonorEmail('jean.dupont@example.com')
            ->setMessage('Merci pour votre travail !')
            ->setStripeSessionId('cs_test_abc123')
            ->setPaymentStatus(Donation::STATUS_PAID);
        
        $this->assertEquals(2500, $donation->getAmount());
        $this->assertEquals(25.0, $donation->getAmountInEuros());
        $this->assertEquals('Jean Dupont', $donation->getDonorName());
        $this->assertEquals('jean.dupont@example.com', $donation->getDonorEmail());
        $this->assertEquals('Merci pour votre travail !', $donation->getMessage());
        $this->assertEquals('cs_test_abc123', $donation->getStripeSessionId());
        $this->assertEquals(Donation::STATUS_PAID, $donation->getPaymentStatus());
    }

    public function testDonationFluency(): void
    {
        $donation = new Donation();
        
        // Vérifier que les setters retournent $this pour permettre le chainage
        $result = $donation->setAmount(1000);
        $this->assertSame($donation, $result);
        
        $result = $donation->setDonorName('Test');
        $this->assertSame($donation, $result);
        
        $result = $donation->setDonorEmail('test@example.com');
        $this->assertSame($donation, $result);
        
        $result = $donation->setMessage('Test message');
        $this->assertSame($donation, $result);
        
        $result = $donation->setStripeSessionId('cs_test');
        $this->assertSame($donation, $result);
        
        $result = $donation->setPaymentStatus(Donation::STATUS_PAID);
        $this->assertSame($donation, $result);
    }

    public function testCreatedAtIsImmutable(): void
    {
        $donation = new Donation();
        $originalCreatedAt = $donation->getCreatedAt();
        
        // Attendre un peu (même si c'est très court)
        usleep(100);
        
        // Créer une nouvelle date
        $newDate = new \DateTimeImmutable('2024-01-01 00:00:00');
        $donation->setCreatedAt($newDate);
        
        // La date peut être changée, mais ce n'est pas recommandé
        // Ce test vérifie juste que le setter fonctionne
        $this->assertEquals($newDate, $donation->getCreatedAt());
        
        // Remettre à la date originale
        $donation->setCreatedAt($originalCreatedAt);
    }
}
