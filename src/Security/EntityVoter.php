<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle d'accès générique aux entités de gestion.
 * Attributs : VIEW, CREATE, EDIT, DELETE.
 * La matrice fine des droits vit ICI — un seul endroit à affiner.
 */
class EntityVoter extends Voter
{
    public const VIEW = 'ENTITY_VIEW';
    public const CREATE = 'ENTITY_CREATE';
    public const EDIT = 'ENTITY_EDIT';
    public const DELETE = 'ENTITY_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array(
            $attribute,
            [self::VIEW, self::CREATE, self::EDIT, self::DELETE],
            true
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false; // anonyme : refusé (le VIEW public se gère au niveau des routes publiques)
        }

        $function = $user->getOfficeFunction();

        // Bureau (président, trésorier, secrétaire) : droits étendus
        $isBureau = in_array($function, [
            OfficeFunction::President,
            OfficeFunction::Treasurer,
            OfficeFunction::Secretary,
        ], true);

        return match ($attribute) {
            self::VIEW   => $function !== OfficeFunction::None, // tout membre réel voit
            self::CREATE => $isBureau,
            self::EDIT   => $this->canEdit($function),
            self::DELETE => $this->canDelete($function),
            default      => false,
        };
    }

    private function canEdit(OfficeFunction $function): bool
    {
        // Le bureau peut éditer ; affinage plus tard (ex. le trésorier n'édite que la compta)
        return in_array($function, [
            OfficeFunction::President,
            OfficeFunction::Secretary,
        ], true);
    }

    private function canDelete(OfficeFunction $function): bool
    {
        // Suppression : président uniquement — l'acte irréversible reste au sommet
        return $function === OfficeFunction::President;
    }
}