<?php

namespace App\Service;

use App\Entity\MembershipFee;
use App\Repository\MembershipFeeRepository;
use Doctrine\ORM\EntityManagerInterface;

class MembershipFeeService
{
    public function __construct(
        private MembershipFeeRepository $feeRepository,
        private EntityManagerInterface $em
    ) {}

    /**
     * Récupère le tarif actuel en centimes
     * @throws \RuntimeException Si aucun tarif n'est défini
     */
    public function getCurrentAmount(): int
    {
        return $this->feeRepository->getCurrentAmount();
    }

    /**
     * Récupère le tarif actuel au format euros (pour l'affichage)
     * @throws \RuntimeException Si aucun tarif n'est défini
     */
    public function getCurrentAmountInEuros(): float
    {
        return $this->getCurrentAmount() / 100;
    }

    /**
     * Récupère le tarif actuel complet
     * @throws \RuntimeException Si aucun tarif n'est défini
     */
    public function getCurrentFee(): MembershipFee
    {
        $fee = $this->feeRepository->findCurrentFee();
        
        if (!$fee) {
            throw new \RuntimeException('Aucun tarif d\'adhésion défini en base de données.');
        }
        
        return $fee;
    }

    /**
     * Met à jour le tarif d'adhésion en créant une nouvelle entrée d'historique
     * Chaque tarif voté en AG est un enregistrement daté, on ne réécrit pas l'histoire.
     */
    public function updateFee(
        int $amount,
        ?\DateTimeImmutable $effectiveFrom = null,
        ?string $notes = null
    ): MembershipFee {
        // Toujours créer un nouveau MembershipFee pour conserver l'historique
        $fee = new MembershipFee();
        
        $fee->setAmount($amount);
        $fee->setUpdatedAt(new \DateTimeImmutable());
        $fee->setEffectiveFrom($effectiveFrom);
        $fee->setNotes($notes);
        
        $this->em->persist($fee);
        $this->em->flush();
        
        return $fee;
    }

    /**
     * Vérifie si un tarif est défini
     */
    public function hasFeeDefined(): bool
    {
        return $this->feeRepository->findCurrentFee() !== null;
    }
}
