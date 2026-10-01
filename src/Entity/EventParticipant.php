<?php

namespace App\Entity;

use App\Enum\ParticipantRole;
use App\Repository\EventParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Participation d'un utilisateur à un événement.
 * 
 * Stocke : qui, quand, quel rôle, présence confirmée.
 * Essentiel pour implémenter ImageVisibility::Participants.
 */
#[ORM\Entity(repositoryClass: EventParticipantRepository::class)]
#[ORM\Table(name: 'event_participant')]
#[ORM\UniqueConstraint(name: 'UNIQ_event_participant', columns: ['event_id', 'user_id'])]
class EventParticipant
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'participants')]
    #[ORM\JoinColumn(name: 'event_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column(length: 100)]
    private ParticipantRole $role = ParticipantRole::Participant;

    #[ORM\Column]
    private bool $attended = false; // Présence confirmée a posteriori

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null; // Notes diverses (Bénévole, participant, etc.)

    public function __construct()
    {
        $this->registeredAt = new \DateTimeImmutable();
    }

    // Getters/Setters

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(\DateTimeImmutable $registeredAt): static
    {
        $this->registeredAt = $registeredAt;
        return $this;
    }

    public function getRole(): ParticipantRole
    {
        return $this->role;
    }

    public function setRole(ParticipantRole $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function hasAttended(): bool
    {
        return $this->attended;
    }

    public function setAttended(bool $attended): static
    {
        $this->attended = $attended;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Retourne l'identifiant composite sous forme de chaîne
     */
    public function getCompositeId(): string
    {
        return $this->event->getId() . '-' . $this->user->getId();
    }

    /**
     * Retourne si ce participant est l'organisateur
     */
    public function isOrganizer(): bool
    {
        return $this->role === ParticipantRole::Organizer;
    }

    /**
     * Retourne si ce participant est un bénévole
     */
    public function isVolunteer(): bool
    {
        return $this->role === ParticipantRole::Volunteer;
    }

    /**
     * Retourne si ce participant est un simple participant
     */
    public function isParticipant(): bool
    {
        return $this->role === ParticipantRole::Participant;
    }
}