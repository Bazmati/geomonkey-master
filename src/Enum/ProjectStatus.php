<?php

namespace App\Enum;

/**
 * Statut public d'un projet (visible par tous, sans détails internes).
 */
enum ProjectStatus: string
{
    case Planned = 'planifie';
    case InProgress = 'en_cours';
    case Completed = 'termine';

    /**
     * Libellé français pour l'interface.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Planned => 'Planifié',
            self::InProgress => 'En cours',
            self::Completed => 'Terminé',
        };
    }

    /**
     * Classe(s) de badge Bootstrap associées au statut.
     */
    public function getBadgeClass(): string
    {
        return match ($this) {
            self::Planned => 'bg-gm-warning text-gm-bg',
            self::InProgress => 'bg-gm-green text-gm-white',
            self::Completed => 'bg-secondary text-white',
        };
    }

    /**
     * Accroche affichée au public pour donner envie de rejoindre l'association.
     */
    public function getPublicTeaser(): string
    {
        return match ($this) {
            self::Planned => 'Ce projet est en préparation — rejoignez l\'association pour participer !',
            self::InProgress => 'Ce projet est en cours de réalisation — rejoignez l\'association pour participer !',
            self::Completed => 'Ce projet est terminé — découvrez les événements et articles qui l\'ont documenté.',
        };
    }

    /**
     * Valeurs pour les formulaires (label => valeur).
     *
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }

        return $choices;
    }
}
