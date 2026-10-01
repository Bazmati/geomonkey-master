<?php

namespace App\Tests\Factory;

use App\Enum\OfficeFunction;
use App\Factory\UserFactory;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFactoryTest extends TestCase
{
    private UserFactory $factory;

    protected function setUp(): void
    {
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')
            ->willReturn('hashed_password');

        $this->factory = new UserFactory($passwordHasher);
    }

    public function testCreateDefaultReturnsPresident(): void
    {
        $user = $this->factory->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(OfficeFunction::President, $user->getOfficeFunction());
        $this->assertSame('user@test.com', $user->getEmail());
    }

    public function testCreateWithCustomFunction(): void
    {
        $user = $this->factory->create(OfficeFunction::Treasurer);

        $this->assertSame(OfficeFunction::Treasurer, $user->getOfficeFunction());
    }

    public function testCreatePresident(): void
    {
        $user = $this->factory->createPresident();

        $this->assertSame(OfficeFunction::President, $user->getOfficeFunction());
        $this->assertSame('president@test.com', $user->getEmail());
        $this->assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testCreateTreasurer(): void
    {
        $user = $this->factory->createTreasurer();

        $this->assertSame(OfficeFunction::Treasurer, $user->getOfficeFunction());
        $this->assertSame('treasurer@test.com', $user->getEmail());
    }

    public function testCreateSecretary(): void
    {
        $user = $this->factory->createSecretary();

        $this->assertSame(OfficeFunction::Secretary, $user->getOfficeFunction());
        $this->assertSame('secretary@test.com', $user->getEmail());
    }

    public function testCreateActiveMember(): void
    {
        $user = $this->factory->createActiveMember();

        $this->assertSame(OfficeFunction::ActiveMember, $user->getOfficeFunction());
        $this->assertSame('member@test.com', $user->getEmail());
    }

    public function testCreateNone(): void
    {
        $user = $this->factory->createNone();

        $this->assertSame(OfficeFunction::None, $user->getOfficeFunction());
        $this->assertSame('visitor@test.com', $user->getEmail());
    }

    public function testCreateSuperAdmin(): void
    {
        $user = $this->factory->createSuperAdmin();

        $this->assertSame(OfficeFunction::President, $user->getOfficeFunction());
        $this->assertSame('superadmin@geomonkey.fr', $user->getEmail());
        $this->assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
        $this->assertSame('Super', $user->getFirstName());
        $this->assertSame('Admin', $user->getLastName());
    }

    public function testCreateWithCustomEmail(): void
    {
        $user = $this->factory->create(OfficeFunction::President, 'custom@email.com');

        $this->assertSame('custom@email.com', $user->getEmail());
    }

    public function testCreateWithCustomPassword(): void
    {
        $user = $this->factory->create(OfficeFunction::President, 'test@email.com', 'custom_password');

        $this->assertSame('hashed_password', $user->getPassword());
    }

    public function testCreateWithCustomRoles(): void
    {
        $user = $this->factory->create(OfficeFunction::President, 'test@email.com', 'password', ['ROLE_CUSTOM']);

        $this->assertContains('ROLE_CUSTOM', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles()); // Ajouté automatiquement
    }
}