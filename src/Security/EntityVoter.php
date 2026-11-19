<?php

namespace App\Security;

use App\Entity\BlogPost;
use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle d'accès générique aux entités de gestion.
 * Attributs : VIEW, CREATE, EDIT, DELETE, PUBLISH, SETTINGS.
 * La matrice fine des droits vit ICI — un seul endroit à affiner.
 */
class EntityVoter extends Voter
{
    public const VIEW = 'ENTITY_VIEW';
    public const CREATE = 'ENTITY_CREATE';
    public const EDIT = 'ENTITY_EDIT';
    public const DELETE = 'ENTITY_DELETE';
    public const PUBLISH = 'ENTITY_PUBLISH';      // mise en ligne : président uniquement
    public const SETTINGS = 'ENTITY_SETTINGS';    // onglet Paramètres : président uniquement
    public const TREASURY = 'ENTITY_TREASURY';    // données financières (dons, future compta) : bureau étendu, trésorier inclus
    public const MESSAGES = 'ENTITY_MESSAGES';    // boîte mail de l'asso (onglet Messages) : bureau étendu

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array(
            $attribute,
            [self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::PUBLISH, self::SETTINGS, self::TREASURY, self::MESSAGES],
            true
        );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false; // anonyme : refusé (le VIEW public se gère au niveau des routes publiques)
        }

        // ROLE_SUPER_ADMIN : clé de secours (break-glass). Passe-partout
        // total, à n'utiliser qu'en cas de blocage de gouvernance
        // (président indisponible ou compromis). Les rôles du token
        // contiennent ROLE_SUPER_ADMIN car la hiérarchie le propage.
        if (\in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $function = $user->getOfficeFunction();

        // RÈGLE DE SÉCURITÉ : un membre sans adhésion active est rétrogradé
        // automatiquement (User::applyMembershipStatus()). On ne fait pas
        // confiance au seul champ en base : on revérifie l'adhésion courante
        // pour les fonctions de bureau, défense en profondeur.
        if ($function !== OfficeFunction::None && !$user->hasActiveMembership()) {
            $user->applyMembershipStatus();
            return false;
        }

        // Bureau (président, trésorier, secrétaire) : droits étendus
        $isBureau = in_array($function, [
            OfficeFunction::President,
            OfficeFunction::VicePresident,
            OfficeFunction::Treasurer,
            OfficeFunction::Secretary,
        ], true);

        return match ($attribute) {
            self::VIEW     => $function !== OfficeFunction::None, // tout membre réel voit
            self::CREATE   => $isBureau,
            self::EDIT     => $this->canEdit($function),
            self::DELETE   => $this->canDelete($function),
            self::PUBLISH  => $this->canPublish($function, $subject),
            self::SETTINGS => $this->canManageSettings($function),
            self::TREASURY => $isBureau, // bureau étendu : président, VP, trésorier, secrétaire
            self::MESSAGES => $isBureau, // boîte mail asso : idem bureau étendu
            default        => false,
        };
    }

    private function canEdit(OfficeFunction $function): bool
    {
        // Le bureau peut éditer ; affinage plus tard (ex. le trésorier n'édite que la compta)
        return in_array($function, [
            OfficeFunction::President,
            OfficeFunction::VicePresident,
            OfficeFunction::Secretary,
        ], true);
    }

    private function canDelete(OfficeFunction $function): bool
    {
        // Suppression : président uniquement — l'acte irréversible reste au sommet
        return $function === OfficeFunction::President;
    }

    /**
     * Mise en ligne (publication) : président uniquement.
     * L'acte qui engage publiquement l'association (blog, réseaux sociaux,
     * galerie publique) reste au sommet, comme la suppression.
     *
     * $subject reste accepté (entité concernée, ex. BlogPost) pour permettre
     * un affinage ultérieur par type d'entité sans casser l'API du voter.
     */
    private function canPublish(OfficeFunction $function, mixed $subject): bool
    {
        if ($function !== OfficeFunction::President) {
            return false;
        }

        // Affinage possible par entité (ex. plus tard : un trésorier publierait
        // les billets purement comptables). Aujourd'hui : aucune exception.
        // if ($subject instanceof BlogPost) { ... }

        return true;
    }

    /**
     * Onglet Paramètres (dont Réseaux sociaux) : président uniquement.
     * Les credentials et la visibilité publique de l'association
     * ne se partagent pas.
     */
    private function canManageSettings(OfficeFunction $function): bool
    {
        return $function === OfficeFunction::President;
    }
}