<?php

namespace App\Security;

use App\Entity\GalleryImage;
use App\Entity\User;
use App\Enum\ImageVisibility;
use App\Enum\OfficeFunction;
use App\Repository\EventParticipantRepository;
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

    public function __construct(
        private EventParticipantRepository $participantRepository
    ) {
    }

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

        // Pour les images publiques, même un anonyme peut voir
        if ($subject instanceof GalleryImage) {
            $visibility = $subject->getVisibility();
            
            // Cas spécial : si l'image est publique ET publiée, même un anonyme peut voir
            if ($attribute === self::VIEW && $visibility === ImageVisibility::Public) {
                return $subject->isPublished();
            }
        }

        if (!$user instanceof User) {
            return false; // Anonyme : accès refusé sauf pour les images publiques (déjà géré ci-dessus)
        }

        $function = $user->getOfficeFunction();

        // Si on a un subject (GalleryImage), appliquer la logique spécifique à l'attribut
        if ($subject instanceof GalleryImage) {
            return match ($attribute) {
                self::VIEW => match ($subject->getVisibility()) {
                    ImageVisibility::Public => $subject->isPublished(), // Tout le monde peut voir (si publiée)
                    ImageVisibility::Members => $subject->isPublished() && $function !== OfficeFunction::None, // Tout membre connecté
                    ImageVisibility::Participants => $subject->isPublished() && $this->canViewAsParticipant($user, $subject),
                    ImageVisibility::Bureau => $subject->isPublished() && $this->isBureau($function), // Seulement le bureau
                    default => false,
                },
                self::PUBLISH => $this->canPublish($user, $subject),
                // Pour CREATE, EDIT, DELETE : on délègue à la matrice générique
                self::CREATE, self::EDIT, self::DELETE => match ($attribute) {
                    self::CREATE => $this->isBureau($function),
                    self::EDIT => $this->canEdit($function),
                    self::DELETE => $this->canDelete($function),
                    default => false,
                },
                default => false,
            };
        }

        // Si pas de subject (pour CREATE par exemple)
        return match ($attribute) {
            self::VIEW => true, // Par défaut autorisé si on arrive ici
            self::CREATE => $this->isBureau($function),
            self::EDIT => $this->canEdit($function),
            self::DELETE => $this->canDelete($function),
            self::PUBLISH => false, // PUBLISH nécessite toujours un GalleryImage
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
     * 
     * Vérifie si l'utilisateur a participé à UN des événements liés à l'image.
     * Utilise une requête optimisée pour vérifier plusieurs événements à la fois.
     */
    private function canViewAsParticipant(User $user, GalleryImage $image): bool
    {
        $events = $image->getEvents()->toArray();
        
        // Si l'image n'est liée à aucun événement, personne ne peut la voir via Participants
        if ($events === []) {
            return false;
        }

        return $this->participantRepository->userParticipatesIn($user, $events);
    }

    /**
     * Vérifie si l'utilisateur peut publier une image.
     * 
     * - Images Public (visible du grand public) : président uniquement
     * - Images à visibilité restreinte (Members, Participants, Bureau) : tout le bureau peut publier
     * 
     * Note : Le contrôleur doit aussi vérifier hasValidConsent() pour les images Public
     * (double barrière : voter + garde applicative).
     */
    private function canPublish(User $user, GalleryImage $image): bool
    {
        $isPresident = $user->getOfficeFunction() === OfficeFunction::President;

        // Image Public (visible du grand public) : président uniquement
        if ($image->getVisibility() === ImageVisibility::Public) {
            return $isPresident;
        }

        // Images à visibilité restreinte : tout le bureau peut publier
        return $this->isBureau($user->getOfficeFunction());
    }
}