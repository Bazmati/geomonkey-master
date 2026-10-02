<?php

namespace App\Entity;

use App\Repository\MembershipRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\Table(name: 'memberships')]
class Membership
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'memberships')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true; // false = expirée ou annulée

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startedAt; // Date de paiement validé

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt; // startedAt + 1 an

    #[ORM\Column(type: Types::INTEGER)] // En centimes !
    private int $amount; // Montant réel payé

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripePaymentId = null; // Traçabilité Stripe

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $renewedAt = null; // Dernier renouvellement

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null; // Pour le cron

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $paymentStatus = null; // paid, failed, pending, etc.

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->expiresAt = (new \DateTimeImmutable())->add(new \DateInterval('P1Y'));
    }

    // --- GETTERS/SETTERS ---
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
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

    public function getStripePaymentId(): ?string
    {
        return $this->stripePaymentId;
    }

    public function setStripePaymentId(?string $stripePaymentId): self
    {
        $this->stripePaymentId = $stripePaymentId;
        return $this;
    }

    public function getRenewedAt(): ?\DateTimeImmutable
    {
        return $this->renewedAt;
    }

    public function setRenewedAt(?\DateTimeImmutable $renewedAt): self
    {
        $this->renewedAt = $renewedAt;
        return $this;
    }

    public function getReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTimeImmutable $reminderSentAt): self
    {
        $this->reminderSentAt = $reminderSentAt;
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(?string $paymentStatus): self
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    // --- MÉTHODES UTILITAIRES ---
    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isAboutToExpire(): bool
    {
        $now = new \DateTimeImmutable();
        $in30Days = $now->add(new \DateInterval('P30D'));
        return $this->expiresAt <= $in30Days && $this->expiresAt > $now;
    }
}
