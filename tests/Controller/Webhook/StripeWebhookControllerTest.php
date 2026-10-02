<?php

namespace App\Tests\Controller\Webhook;

use App\Entity\Membership;
use App\Entity\User;
use App\Enum\OfficeFunction;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la logique métier du webhook Stripe
 * 
 * Teste que les utilisateurs et memberships sont correctement créés/mis à jour.
 */
class StripeWebhookControllerTest extends TestCase
{
    /**
     * Test que la méthode hasActiveMembership retourne true quand l'utilisateur a une membership active
     */
    public function testUserHasActiveMembership(): void
    {
        $user = new User();
        
        // Ajouter une membership active non expirée
        $membership = new Membership();
        $membership->setIsActive(true);
        $membership->setExpiresAt(new \DateTimeImmutable('+1 year'));
        
        $user->addMembership($membership);
        
        $this->assertTrue($user->hasActiveMembership(), 'User should have active membership');
    }

    /**
     * Test que la méthode hasActiveMembership retourne false quand l'utilisateur a une membership expirée
     */
    public function testUserHasExpiredMembership(): void
    {
        $user = new User();
        
        // Ajouter une membership expirée
        $membership = new Membership();
        $membership->setIsActive(true);
        $membership->setExpiresAt(new \DateTimeImmutable('-1 day'));
        
        $user->addMembership($membership);
        
        $this->assertFalse($user->hasActiveMembership(), 'User should not have active membership with expired date');
    }

    /**
     * Test que la méthode hasActiveMembership retourne false quand l'utilisateur n'a aucune membership
     */
    public function testUserWithNoMembershipHasNoActiveMembership(): void
    {
        $user = new User();
        
        $this->assertFalse($user->hasActiveMembership(), 'User with no membership should not have active membership');
    }

    /**
     * Test que la méthode hasActiveMembership retourne false quand la membership n'est pas active
     */
    public function testUserWithInactiveMembershipHasNoActiveMembership(): void
    {
        $user = new User();
        
        // Ajouter une membership inactive
        $membership = new Membership();
        $membership->setIsActive(false);
        $membership->setExpiresAt(new \DateTimeImmutable('+1 year'));
        
        $user->addMembership($membership);
        
        $this->assertFalse($user->hasActiveMembership(), 'User should not have active membership when isActive is false');
    }

    /**
     * Test que la méthode hasActiveMembership retourne true quand plusieurs memberships et une est active
     */
    public function testUserWithMultipleMembershipsHasActiveMembership(): void
    {
        $user = new User();
        
        // Ajouter une membership expirée
        $expiredMembership = new Membership();
        $expiredMembership->setIsActive(true);
        $expiredMembership->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $user->addMembership($expiredMembership);
        
        // Ajouter une membership active
        $activeMembership = new Membership();
        $activeMembership->setIsActive(true);
        $activeMembership->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $user->addMembership($activeMembership);
        
        $this->assertTrue($user->hasActiveMembership(), 'User should have active membership if at least one is active');
    }

    /**
     * Test que la méthode applyMembershipStatus rétrograde un utilisateur sans adhésion active
     */
    public function testApplyMembershipStatusDowngradesUserWithoutActiveMembership(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        
        // Appliquer la règle - devrait rétrograder en None
        $user->applyMembershipStatus();
        
        $this->assertEquals(OfficeFunction::None, $user->getOfficeFunction(), 
            'User should be downgraded to None when no active membership');
    }

    /**
     * Test que la méthode applyMembershipStatus rétrograde un président sans adhésion active
     * (les fonctions de bureau sans adhésion active sont rétrogradées)
     */
    public function testApplyMembershipStatusDowngradesPresidentWithoutActiveMembership(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        
        // Appliquer la règle - DOIT rétrograder car pas de membership active
        $user->applyMembershipStatus();
        
        $this->assertEquals(OfficeFunction::None, $user->getOfficeFunction(), 
            'President without active membership should be downgraded to None');
    }

    /**
     * Test que la méthode applyMembershipStatus ne rétrograde pas un président AVEC adhésion active
     */
    public function testApplyMembershipStatusDoesNotDowngradePresidentWithActiveMembership(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::President);
        
        // Ajouter une membership active
        $membership = new Membership();
        $membership->setIsActive(true);
        $membership->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $user->addMembership($membership);
        
        // Appliquer la règle - ne devrait pas changer
        $user->applyMembershipStatus();
        
        $this->assertEquals(OfficeFunction::President, $user->getOfficeFunction(),
            'President with active membership should not be downgraded');
    }

    /**
     * Test que la méthode applyMembershipStatus ne rétrograde pas un active member AVEC adhésion active
     */
    public function testApplyMembershipStatusDoesNotDowngradeActiveMemberWithActiveMembership(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::ActiveMember);
        
        // Ajouter une membership active
        $membership = new Membership();
        $membership->setIsActive(true);
        $membership->setExpiresAt(new \DateTimeImmutable('+1 year'));
        $user->addMembership($membership);
        
        // Appliquer la règle
        $user->applyMembershipStatus();
        
        $this->assertEquals(OfficeFunction::ActiveMember, $user->getOfficeFunction(),
            'Active member with active membership should not be downgraded');
    }

    /**
     * Test que la méthode applyMembershipStatus ne change rien si l'utilisateur est déjà None
     */
    public function testApplyMembershipStatusDoesNotChangeNone(): void
    {
        $user = new User();
        $user->setOfficeFunction(OfficeFunction::None);
        
        // Appliquer la règle
        $user->applyMembershipStatus();
        
        $this->assertEquals(OfficeFunction::None, $user->getOfficeFunction(),
            'None should remain None');
    }

    /**
     * Test que la relation bidirectionnelle entre User et Membership fonctionne
     */
    public function testBidirectionalRelationshipUserMembership(): void
    {
        $user = new User();
        $membership = new Membership();
        
        // Ajouter la membership à l'utilisateur
        $user->addMembership($membership);
        
        // Vérifier que la membership a bien l'utilisateur
        $this->assertSame($user, $membership->getUser(), 
            'Membership should have the user after addMembership');
        
        // Vérifier que l'utilisateur a la membership
        $this->assertCount(1, $user->getMemberships(), 
            'User should have one membership');
        $this->assertSame($membership, $user->getMemberships()->first(), 
            'User should have the correct membership');
    }

    /**
     * Test que removeMembership fonctionne correctement
     */
    public function testRemoveMembership(): void
    {
        $user = new User();
        $membership = new Membership();
        
        // Ajouter la membership
        $user->addMembership($membership);
        $this->assertCount(1, $user->getMemberships());
        
        // Retirer la membership
        $user->removeMembership($membership);
        $this->assertCount(0, $user->getMemberships(), 
            'User should have no memberships after removal');
    }

    /**
     * Test que le champ membershipValidUntil peut être défini
     */
    public function testSetMembershipValidUntil(): void
    {
        $user = new User();
        $date = new \DateTime('2027-10-02');
        
        $user->setMembershipValidUntil($date);
        
        $this->assertSame($date, $user->getMembershipValidUntil(),
            'membershipValidUntil should be set correctly');
    }

    /**
     * Test que le champ registrationValidatedAt peut être défini
     */
    public function testSetRegistrationValidatedAt(): void
    {
        $user = new User();
        $date = new \DateTimeImmutable();
        
        $user->setRegistrationValidatedAt($date);
        
        $this->assertSame($date, $user->getRegistrationValidatedAt(),
            'registrationValidatedAt should be set correctly');
    }
}
