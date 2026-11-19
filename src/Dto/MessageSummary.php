<?php

namespace App\Dto;

/**
 * Résumé d'un email pour la liste du backoffice.
 * Transporte uniquement ce que la liste affiche — pas l'objet complet
 * de la bibliothèque IMAP — pour que le template reste simple et que
 * les données volumineuses (corps, pièces jointes) ne transitent pas.
 */
final readonly class MessageSummary
{
    public function __construct(
        public int $uid,
        public string $from,
        public string $subject,
        public ?\DateTimeInterface $date,
        public bool $seen,
    ) {}
}
