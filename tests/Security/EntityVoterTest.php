<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\OfficeFunction;
use App\Security\EntityVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class EntityVoterTest extends TestCase
{
    private EntityVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new EntityVoter();
    }

    /**
     * Crée un token pour les tests
     */
    private function createToken(User $user): TestBrowserToken
    {
        return new TestBrowserToken($user->getRoles(), $user);
    }

    // Tests pour VIEW
    public function testViewAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewAllowedForTreasurer(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewAllowedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewAllowedForActiveMember(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testViewDeniedForNone(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testViewDeniedForAnonymous(): void
    {
        // Créer un token sans user
        $token = new TestBrowserToken([]);

        $result = $this->voter->vote($token, null, [EntityVoter::VIEW]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // Tests pour CREATE
    public function testCreateAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateAllowedForTreasurer(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateAllowedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testCreateDeniedForActiveMember(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testCreateDeniedForNone(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::CREATE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // Tests pour EDIT
    public function testEditAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditAllowedForSecretary(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testEditDeniedForTreasurer(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForActiveMember(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testEditDeniedForNone(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::EDIT]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // Tests pour DELETE
    public function testDeleteAllowedForPresident(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testTreasurerCannotDelete(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testSecretaryCannotDelete(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testActiveMemberCannotDelete(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testNoneCannotDelete(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, [EntityVoter::DELETE]);
        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    // Test pour vérifier que les attributs non supportés retournent ACCESS_ABSTAIN
    public function testUnsupportedAttributeReturnsAbstain(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, null, ['UNSUPPORTED_ATTRIBUTE']);
        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}