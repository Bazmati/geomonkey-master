<?php

namespace App\Enum;

/**
 * Niveaux de visibilité pour les images de la galerie.
 * 
 * Public: tout le monde, RGPD conforme (pas de visages identifiables sans consentement)
 * Members: connectés, tout statut
 * Participants: ont participé à un event/projet lié
 * Bureau: uniquement le bureau
 */
enum ImageVisibility: string
{
    case Public = 'public';
    case Members = 'membres';
    case Participants = 'participants';
    case Bureau = 'bureau';

    /**
     * Retourne le label en français pour l'interface utilisateur
     */
    public function getLabel(): string
    {
        return match($this) {
            self::Public => 'Publique',
            self::Members => 'Membres',
            self::Participants => 'Participants',
            self::Bureau => 'Bureau',
        };
    }

    /**
     * Retourne la description détaillée
     */
    public function getDescription(): string
    {
        return match($this) {
            self::Public => 'Visible par tout le monde (RGPD conforme)',
            self::Members => 'Visible par tous les membres connectés',
            self::Participants => 'Visible uniquement par les participants à l\'événement/projet lié',
            self::Bureau => 'Visible uniquement par les membres du bureau',
        };
    }

    /**
     * Retourne les niveaux dans l'ordre de visibilité croissante
     */
    public static function all(): array
    {
        return [
            self::Public,
            self::Members,
            self::Participants,
            self::Bureau,
        ];
    }

    /**
     * Vérifie si ce niveau est plus restrictif qu'un autre
     */
    public function isMoreRestrictiveThan(self $other): bool
    {
        $order = array_flip(self::all());
        return $order[$this] > $order[$other];
    }

    /**
     * Retourne le niveau le plus restrictif entre deux
     */
    public static function mostRestrictive(self $a, self $b): self
    {
        return $a->isMoreRestrictiveThan($b) ? $a : $b;
    }
}