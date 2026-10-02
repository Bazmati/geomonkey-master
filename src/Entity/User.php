<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Enum\OfficeFunction;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30, enumType: OfficeFunction::class)]
    private OfficeFunction $officeFunction = OfficeFunction::None;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    private ?string $firstName = null;

    #[ORM\Column(length: 50)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $membershipValidUntil = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // === NOUVEAUX CHAMPS POUR L'ADHÉSION ===
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Membership::class)]
    private Collection $memberships;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $requestedFunction = null; // 'membre_actif' ou null

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isRegistrationConfirmed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $registrationValidatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOfficeFunction(): OfficeFunction
    {
        return $this->officeFunction;
    }

    public function setOfficeFunction(OfficeFunction $officeFunction): static
    {
        $this->officeFunction = $officeFunction;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getMembershipValidUntil(): ?\DateTime
    {
        return $this->membershipValidUntil;
    }

    public function setMembershipValidUntil(?\DateTime $membershipValidUntil): static
    {
        $this->membershipValidUntil = $membershipValidUntil;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getOfficeFunctionLabel(): string
    {
        return match ($this->officeFunction) {
            OfficeFunction::President => 'Président',
            OfficeFunction::Treasurer => 'Trésorier',
            OfficeFunction::Secretary => 'Secrétaire',
            OfficeFunction::ActiveMember => 'Membre actif',
            OfficeFunction::None => '',
        };
    }

    // === MÉTHODES POUR L'ADHÉSION ===

    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(Membership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setUser($this);
        }
        return $this;
    }

    public function removeMembership(Membership $membership): self
    {
        if ($this->memberships->removeElement($membership)) {
            if ($membership->getUser() === $this) {
                $membership->setUser(null);
            }
        }
        return $this;
    }

    public function getRequestedFunction(): ?string
    {
        return $this->requestedFunction;
    }

    public function setRequestedFunction(?string $requestedFunction): self
    {
        $this->requestedFunction = $requestedFunction;
        return $this;
    }

    public function isRegistrationConfirmed(): bool
    {
        return $this->isRegistrationConfirmed;
    }

    public function setIsRegistrationConfirmed(bool $isRegistrationConfirmed): self
    {
        $this->isRegistrationConfirmed = $isRegistrationConfirmed;
        return $this;
    }

    public function getRegistrationValidatedAt(): ?\DateTimeImmutable
    {
        return $this->registrationValidatedAt;
    }

    public function setRegistrationValidatedAt(?\DateTimeImmutable $registrationValidatedAt): self
    {
        $this->registrationValidatedAt = $registrationValidatedAt;
        return $this;
    }

    // === MÉTHODES MÉTIER ===

    /**
     * Retourne true si l'utilisateur a demandé un statut nécessitant validation
     */
    public function needsBureauValidation(): bool
    {
        return $this->requestedFunction === 'membre_actif';
    }

    /**
     * Retourne les statuts disponibles pour le formulaire public
     */
    public static function getAvailableRequestedFunctions(): array
    {
        return ['membre_actif']; // Seule option publique (null = visiteur)
    }

    /**
     * Vérifie si l'utilisateur a une adhésion active (non expirée)
     */
    public function hasActiveMembership(): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->isActive() && !$membership->isExpired()) {
                return true;
            }
        }
        return false;
    }

    /**
     * RÈGLE CENTRALE : Pas d'adhésion active → rétrogradation automatique
     * Appelée après expiration ou retrait de consentement
     */
    public function applyMembershipStatus(): void
    {
        if (!$this->hasActiveMembership() && $this->getOfficeFunction() !== OfficeFunction::None) {
            $this->setOfficeFunction(OfficeFunction::None);
        }
    }
}
