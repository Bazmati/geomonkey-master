<?php

namespace App\Enum;

/**
 * État d'une tâche de projet (suivi interne, 3 colonnes type Trello).
 */
enum ProjectTaskState: string
{
    case Todo = 'a_faire';
    case Doing = 'en_cours';
    case Done = 'fait';

    /**
     * Libellé français pour l'interface.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Todo => 'À faire',
            self::Doing => 'En cours',
            self::Done => 'Fait',
        };
    }

    /**
     * Classe(s) de badge Bootstrap associées à l'état.
     */
    public function getBadgeClass(): string
    {
        return match ($this) {
            self::Todo => 'bg-gm-warning text-gm-bg',
            self::Doing => 'bg-gm-info text-gm-bg',
            self::Done => 'bg-gm-green text-gm-white',
        };
    }

    /**
     * Icône Bootstrap associée à l'état.
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::Todo => 'bi-circle',
            self::Doing => 'bi-arrow-repeat',
            self::Done => 'bi-check-circle-fill',
        };
    }

    /**
     * Les 3 états, dans l'ordre des colonnes du tableau de suivi.
     *
     * @return array<self>
     */
    public static function boardColumns(): array
    {
        return [self::Todo, self::Doing, self::Done];
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
