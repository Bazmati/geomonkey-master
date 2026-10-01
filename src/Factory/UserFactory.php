<?php

namespace App\Factory;

use App\Entity\User;
use App\Enum\OfficeFunction;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Factory pour créer des utilisateurs de test avec des rôles prédéfinis.
 * Simplifie grandement l'écriture des tests.
 */
class UserFactory
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * Crée un utilisateur avec la fonction de bureau spécifiée.
     * Par défaut : Président (pour éviter les problèmes de suppression)
     */
    public function create(
        OfficeFunction $officeFunction = OfficeFunction::President,
        string $email = 'user@test.com',
        string $password = 'password',
        array $roles = ['ROLE_USER']
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setOfficeFunction($officeFunction);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles($roles);
        $user->setFirstName('Test');
        $user->setLastName('User');

        return $user;
    }

    /**
     * Crée un président (par défaut pour les tests)
     */
    public function createPresident(
        string $email = 'president@test.com',
        string $password = 'password'
    ): User {
        return $this->create(OfficeFunction::President, $email, $password, ['ROLE_ADMIN']);
    }

    /**
     * Crée un trésorier
     */
    public function createTreasurer(
        string $email = 'treasurer@test.com',
        string $password = 'password'
    ): User {
        return $this->create(OfficeFunction::Treasurer, $email, $password);
    }

    /**
     * Crée un secrétaire
     */
    public function createSecretary(
        string $email = 'secretary@test.com',
        string $password = 'password'
    ): User {
        return $this->create(OfficeFunction::Secretary, $email, $password);
    }

    /**
     * Crée un membre actif
     */
    public function createActiveMember(
        string $email = 'member@test.com',
        string $password = 'password'
    ): User {
        return $this->create(OfficeFunction::ActiveMember, $email, $password);
    }

    /**
     * Crée un utilisateur sans fonction (None)
     */
    public function createNone(
        string $email = 'visitor@test.com',
        string $password = 'password'
    ): User {
        return $this->create(OfficeFunction::None, $email, $password);
    }

    /**
     * Crée un super-admin de secours (Président + ROLE_SUPER_ADMIN)
     * À utiliser uniquement pour les situations d'urgence
     */
    public function createSuperAdmin(
        string $email = 'superadmin@geomonkey.fr',
        string $password = 'SuperSecret123!'
    ): User {
        $user = $this->create(OfficeFunction::President, $email, $password, ['ROLE_SUPER_ADMIN']);
        $user->setFirstName('Super');
        $user->setLastName('Admin');
        
        return $user;
    }
}