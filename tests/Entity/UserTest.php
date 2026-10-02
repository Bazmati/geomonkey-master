<?php

namespace App\Tests\Entity;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        
        $this->assertNull($user->getId());
        $this->assertEquals(OfficeFunction::None, $user->getOfficeFunction());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertCount(0, $user->getMemberships());
    }

    public function testOfficeFunctionLabel(): void
    {
        $user = new User();
        
        $user->setOfficeFunction(OfficeFunction::President);
        $this->assertEquals('Président', $user->getOfficeFunctionLabel());
        
        $user->setOfficeFunction(OfficeFunction::Treasurer);
        $this->assertEquals('Trésorier', $user->getOfficeFunctionLabel());
        
        $user->setOfficeFunction(OfficeFunction::Secretary);
        $this->assertEquals('Secrétaire', $user->getOfficeFunctionLabel());
        
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $this->assertEquals('Membre actif', $user->getOfficeFunctionLabel());
        
        $user->setOfficeFunction(OfficeFunction::None);
        $this->assertEquals('', $user->getOfficeFunctionLabel());
    }

    public function testNeedsBureauValidation(): void
    {
        $user = new User();
        
        // Par défaut, requestedFunction est null
        $this->assertFalse($user->needsBureauValidation());
        
        // Si membre_actif, nécessite validation
        $user->setRequestedFunction('membre_actif');
        $this->assertTrue($user->needsBureauValidation());
        
        // Si autre valeur ou null, pas de validation
        $user->setRequestedFunction(null);
        $this->assertFalse($user->needsBureauValidation());
    }

    public function testHasActiveMembership(): void
    {
        $user = new User();
        
        // Par défaut, pas de membership
        $this->assertFalse($user->hasActiveMembership());
        
        // Ajouter une membership active non expirée
        $membership = $this->createMock(Membership::class);
        $membership->method('isActive')->willReturn(true);
        $membership->method('isExpired')->willReturn(false);
        
        $user->addMembership($membership);
        $this->assertTrue($user->hasActiveMembership());
        
        // Ajouter une membership expirée
        $expiredMembership = $this->createMock(Membership::class);
        $expiredMembership->method('isActive')->willReturn(true);
        $expiredMembership->method('isExpired')->willReturn(true);
        
        $user->addMembership($expiredMembership);
        // Doit toujours retourner true car il y a une membership active
        $this->assertTrue($user->hasActiveMembership());
    }

    public function testApplyMembershipStatus(): void
    {
        $user = new User();
        
        // Si pas d'adhésion active et fonction non None, doit passer à None
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $user->applyMembershipStatus();
        $this->assertEquals(OfficeFunction::None, $user->getOfficeFunction());
        
        // Si adhésion active, ne doit pas changer
        $membership = $this->createMock(Membership::class);
        $membership->method('isActive')->willReturn(true);
        $membership->method('isExpired')->willReturn(false);
        $user->addMembership($membership);
        
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        $user->applyMembershipStatus();
        $this->assertEquals(OfficeFunction::ActiveMember, $user->getOfficeFunction());
    }

    public function testMembershipManagement(): void
    {
        $user = new User();
        $membership = new Membership();
        
        // Ajouter une membership
        $user->addMembership($membership);
        $this->assertCount(1, $user->getMemberships());
        $this->assertEquals($user, $membership->getUser());
        
        // Retirer une membership
        $user->removeMembership($membership);
        $this->assertCount(0, $user->getMemberships());
    }

    public function testRgpdFields(): void
    {
        $user = new User();
        
        // Par défaut
        $this->assertNull($user->getTermsAcceptedAt());
        $this->assertFalse($user->getImageConsent());
        $this->assertNull($user->getImageConsentAt());
        $this->assertFalse($user->getWhatsappInviteSent());
        
        // Définir les valeurs
        $now = new \DateTimeImmutable();
        $user->setTermsAcceptedAt($now);
        $user->setImageConsent(true);
        $user->setImageConsentAt($now);
        $user->setWhatsappInviteSent(true);
        
        $this->assertEquals($now, $user->getTermsAcceptedAt());
        $this->assertTrue($user->getImageConsent());
        $this->assertEquals($now, $user->getImageConsentAt());
        $this->assertTrue($user->getWhatsappInviteSent());
    }

    public function testRegistrationFields(): void
    {
        $user = new User();
        
        // Par défaut
        $this->assertFalse($user->isRegistrationConfirmed());
        $this->assertNull($user->getRegistrationValidatedAt());
        $this->assertNull($user->getRequestedFunction());
        
        // Définir les valeurs
        $user->setIsRegistrationConfirmed(true);
        $user->setRegistrationValidatedAt(new \DateTimeImmutable());
        $user->setRequestedFunction('membre_actif');
        
        $this->assertTrue($user->isRegistrationConfirmed());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getRegistrationValidatedAt());
        $this->assertEquals('membre_actif', $user->getRequestedFunction());
    }
}
