<?php

namespace App\Enum;

/**
 * Rôles possibles pour un participant à un événement.
 * 
 * Utilisé pour typer et valider les rôles dans EventParticipant.
 */
enum ParticipantRole: string
{
    case Organizer = 'organisateur';
    case Volunteer = 'benevole';
    case Participant = 'participant';

    /**
     * Retourne le label en français pour l'interface utilisateur
     */
    public function getLabel(): string
    {
        return match($this) {
            self::Organizer => 'Organisateur',
            self::Volunteer => 'Bénévole',
            self::Participant => 'Participant',
        };
    }

    /**
     * Retourne toutes les valeurs possibles
     * @return array<self>
     */
    public static function all(): array
    {
        return [
            self::Organizer,
            self::Volunteer,
            self::Participant,
        ];
    }

    /**
     * Retourne les valeurs possibles sous forme de tableau associatif pour les formulaires
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::all() as $role) {
            $choices[$role->getLabel()] = $role->value;
        }
        return $choices;
    }

    /**
     * Crée un ParticipantRole à partir d'une string
     */
    public static function fromString(string $role): ?self
    {
        foreach (self::all() as $participantRole) {
            if ($participantRole->value === $role || $participantRole->name === $role) {
                return $participantRole;
            }
        }
        return null;
    }
}
