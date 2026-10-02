<?php

namespace App\Entity;

use App\Repository\MembershipFeeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MembershipFeeRepository::class)]
#[ORM\Table(name: 'membership_fees')]
class MembershipFee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive(message: "Le montant doit être positif")]
    #[Assert\NotBlank]
    private int $amount; // **En centimes** (ex: 1500 = 15,00€)

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveFrom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null; // Ex: "Tarif 2025 voté en AG du 15/10/2024"

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getEffectiveFrom(): ?\DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(?\DateTimeImmutable $effectiveFrom): self
    {
        $this->effectiveFrom = $effectiveFrom;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }
}
