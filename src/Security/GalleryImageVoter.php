<?php

namespace App\Security;

use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Enum\OfficeFunction;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour contrôler l'accès aux images de la galerie.
 * 
 * Règles :
 * - Public : visible par tout le monde
 * - Members : visible par les membres connectés
 * - Participants : visible par les participants aux événements liés
 * - Bureau : visible uniquement par le bureau
 * 
 * Pour les actions (EDIT, DELETE) : cascade selon la matrice EntityVoter
 */
class GalleryImageVoter extends Voter
{
    public const VIEW = 'GALLERY_IMAGE_VIEW';
    public const CREATE = 'GALLERY_IMAGE_CREATE';
    public const EDIT = 'GALLERY_IMAGE_EDIT';
    public const DELETE = 'GALLERY_IMAGE_DELETE';
    public const PUBLISH = 'GALLERY_IMAGE_PUBLISH';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::PUBLISH
        ], true) && ($subject === null || $subject instanceof GalleryImage);
    }

    protected function voteOnAttribute(
        string $attribute, 
        mixed $subject, 
        TokenInterface $token, 
        ?Vote $vote = null
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false; // Anonyme : seulement les images publiques
        }

        $function = $user->getOfficeFunction();

        // Pour les images publiques, tout le monde peut voir
        if ($subject instanceof GalleryImage) {
            $visibility = $subject->getVisibility();
            
            // Cas spécial : si l'image est publique, même un anonyme peut voir
            if ($attribute === self::VIEW && $visibility === ImageVisibility::Public) {
                return true;
            }

            // Logique de visibilité basée sur le rôle de l'utilisateur
            return match ($visibility) {
                ImageVisibility::Public => true, // Tout le monde peut voir
                ImageVisibility::Members => $function !== OfficeFunction::None, // Tout membre connecté
                ImageVisibility::Participants => $this->canViewAsParticipant($user, $subject), // À implémenter
                ImageVisibility::Bureau => $this->isBureau($function), // Seulement le bureau
                default => false,
            };
        }

        // Si pas de subject (pour CREATE par exemple)
        return match ($attribute) {
            self::VIEW => true, // Par défaut autorisé si on arrive ici
            self::CREATE => $this->isBureau($function),
            self::EDIT => $this->canEdit($function),
            self::DELETE => $this->canDelete($function),
            self::PUBLISH => $this->isBureau($function),
            default => false,
        };
    }

    /**
     * Vérifie si l'utilisateur fait partie du bureau
     */
    private function isBureau(OfficeFunction $function): bool
    {
        return in_array($function, [
            OfficeFunction::President,
            OfficeFunction::Treasurer,
            OfficeFunction::Secretary,
        ], true);
    }

    /**
     * Vérifie si l'utilisateur peut éditer (Président + Secrétaire)
     */
    private function canEdit(OfficeFunction $function): bool
    {
        return in_array($function, [
            OfficeFunction::President,
            OfficeFunction::Secretary,
        ], true);
    }

    /**
     * Vérifie si l'utilisateur peut supprimer (Président uniquement)
     */
    private function canDelete(OfficeFunction $function): bool
    {
        return $function === OfficeFunction::President;
    }

    /**
     * Vérifie si l'utilisateur peut voir l'image en tant que participant
     * NOTE: À implémenter quand la modélisation des participants sera prête
     * 
     * Pour l'instant, retourne false car les participants ne sont pas encore modélisés
     * Quand EventParticipant existera, on pourra vérifier:
     * - Si l'image est liée à un événement
     * - Si l'utilisateur a participé à cet événement
     */
    private function canViewAsParticipant(User $user, GalleryImage $image): bool
    {
        // TODO: Implémenter quand EventParticipant sera modélisé
        // Exemple de logique future :
        // foreach ($image->getEvents() as $event) {
        //     if ($eventParticipantRepository->userParticipatedInEvent($user, $event)) {
        //         return true;
        //     }
        // }
        
        // Pour l'instant, on utilise le bureau comme fallback
        // car c'est le seul moyen de voir les images Participants
        return $this->isBureau($user->getOfficeFunction());
    }
}