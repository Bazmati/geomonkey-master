<?php

namespace App\Entity;

use App\Repository\DonationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DonationRepository::class)]
#[ORM\Table(name: 'donations')]
class Donation
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $amount; // **En centimes** (ex: 2000 = 20,00€)

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $feeAmount = null; // Frais Stripe en centimes (ex: 27 = 0,27€)

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $netAmount = null; // Net perçu en centimes (ex: 73 = 0,73€)

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $donorName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $donorEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $paymentStatus = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // --- GETTERS/SETTERS ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAmountInEuros(): float
    {
        return $this->amount / 100;
    }

    public function getFeeAmount(): ?int
    {
        return $this->feeAmount;
    }

    public function setFeeAmount(?int $feeAmount): self
    {
        $this->feeAmount = $feeAmount;
        return $this;
    }

    public function getNetAmount(): ?int
    {
        return $this->netAmount;
    }

    public function setNetAmount(?int $netAmount): self
    {
        $this->netAmount = $netAmount;
        return $this;
    }

    public function getNetInEuros(): ?float
    {
        return $this->netAmount !== null ? $this->netAmount / 100 : null;
    }

    public function getDonorName(): ?string
    {
        return $this->donorName;
    }

    public function setDonorName(?string $donorName): self
    {
        $this->donorName = $donorName;
        return $this;
    }

    public function getDonorEmail(): ?string
    {
        return $this->donorEmail;
    }

    public function setDonorEmail(?string $donorEmail): self
    {
        $this->donorEmail = $donorEmail;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): self
    {
        $this->stripeSessionId = $stripeSessionId;
        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
