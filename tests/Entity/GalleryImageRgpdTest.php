<?php

namespace App\Tests\Entity;

use App\Entity\GalleryImage;
use App\Enum\ImageVisibility;
use PHPUnit\Framework\TestCase;

/**
 * Tests dédiés à la conformité RGPD dans GalleryImage.
 * Ces tests vérifient que le consentement est correctement géré et que
 * la rétrogradation automatique fonctionne comme attendu.
 */
class GalleryImageRgpdTest extends TestCase
{
    public function testNewGalleryImageHasNoConsent(): void
    {
        $image = new GalleryImage();
        
        $this->assertFalse($image->hasRgpdConsent());
        $this->assertNull($image->getConsentedAt());
        $this->assertFalse($image->hasValidConsent());
    }

    public function testGiveConsentSetsBothFields(): void
    {
        $image = new GalleryImage();
        
        $this->assertFalse($image->hasValidConsent());
        
        $image->giveConsent();
        
        $this->assertTrue($image->hasRgpdConsent());
        $this->assertNotNull($image->getConsentedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $image->getConsentedAt());
        $this->assertTrue($image->hasValidConsent());
    }

    public function testWithdrawConsentClearsConsent(): void
    {
        $image = new GalleryImage();
        $image->giveConsent();
        
        $this->assertTrue($image->hasValidConsent());
        
        $image->withdrawConsent();
        
        $this->assertFalse($image->hasRgpdConsent());
        $this->assertNull($image->getConsentedAt());
        $this->assertNull($image->getConsentDetail());
        $this->assertFalse($image->hasValidConsent());
    }

    public function testWithdrawConsentDowngradesPublicToMembers(): void
    {
        $image = new GalleryImage();
        $image->setVisibility(ImageVisibility::Public);
        $image->giveConsent();
        
        $this->assertSame(ImageVisibility::Public, $image->getVisibility());
        
        $image->withdrawConsent();
        
        // L'image doit avoir été rétrogradée de Public à Members
        $this->assertSame(ImageVisibility::Members, $image->getVisibility());
        $this->assertFalse($image->hasValidConsent());
    }

    public function testWithdrawConsentPreservesMembersVisibility(): void
    {
        $image = new GalleryImage();
        $image->setVisibility(ImageVisibility::Members);
        $image->giveConsent();
        
        $image->withdrawConsent();
        
        // Une image Members reste Members après retrait du consentement
        $this->assertSame(ImageVisibility::Members, $image->getVisibility());
    }

    public function testWithdrawConsentPreservesBureauVisibility(): void
    {
        $image = new GalleryImage();
        $image->setVisibility(ImageVisibility::Bureau);
        $image->giveConsent();
        
        $image->withdrawConsent();
        
        // Une image Bureau reste Bureau après retrait du consentement
        $this->assertSame(ImageVisibility::Bureau, $image->getVisibility());
    }

    public function testWithdrawConsentPreservesParticipantsVisibility(): void
    {
        $image = new GalleryImage();
        $image->setVisibility(ImageVisibility::Participants);
        $image->giveConsent();
        
        $image->withdrawConsent();
        
        // Une image Participants reste Participants après retrait du consentement
        $this->assertSame(ImageVisibility::Participants, $image->getVisibility());
    }

    public function testHasValidConsentRequiresBothConsentAndDate(): void
    {
        $image = new GalleryImage();
        
        // Seule la consentement coché, pas de date → invalide
        $image->setRgpdConsent(true);
        $this->assertFalse($image->hasValidConsent());
        
        // Seule la date, pas de consentement → invalide
        $image->setRgpdConsent(false);
        $image->setConsentedAt(new \DateTimeImmutable());
        $this->assertFalse($image->hasValidConsent());
        
        // Les deux → valide
        $image->setRgpdConsent(true);
        $this->assertTrue($image->hasValidConsent());
    }

    public function testCanBePublicUsesHasValidConsent(): void
    {
        $image = new GalleryImage();
        
        // canBePublic() délègue à hasValidConsent()
        $this->assertFalse($image->canBePublic());
        
        $image->giveConsent();
        $this->assertTrue($image->canBePublic());
        
        $image->withdrawConsent();
        $this->assertFalse($image->canBePublic());
    }

    /**
     * RGPD : consentDetail doit être CONSERVÉ pour traçabilité après retrait
     * On garde la preuve du consentement initial même après retrait
     */
    public function testConsentDetailIsPreservedOnWithdrawForRgpdTraceability(): void
    {
        $image = new GalleryImage();
        $image->giveConsent();
        $image->setConsentDetail('Consentement par email le 15/03/2024 - Marie D.');
        
        $this->assertSame('Consentement par email le 15/03/2024 - Marie D.', $image->getConsentDetail());
        
        $image->withdrawConsent();
        
        // consentDetail doit être CONSERVÉ pour conformité RGPD (preuve du consentement initial)
        $this->assertSame('Consentement par email le 15/03/2024 - Marie D.', $image->getConsentDetail());
        // Mais les flags de consentement sont bien désactivés
        $this->assertFalse($image->hasRgpdConsent());
        $this->assertNull($image->getConsentedAt());
        $this->assertFalse($image->hasValidConsent());
    }

    public function testMultipleConsentCycles(): void
    {
        $image = new GalleryImage();
        
        // Premier consentement
        $image->giveConsent();
        $firstDate = $image->getConsentedAt();
        $this->assertTrue($image->hasValidConsent());
        
        // Retrait
        $image->withdrawConsent();
        $this->assertFalse($image->hasValidConsent());
        
        // Deuxième consentement
        $image->giveConsent();
        $secondDate = $image->getConsentedAt();
        $this->assertTrue($image->hasValidConsent());
        
        // Les dates doivent être différentes
        $this->assertNotSame($firstDate, $secondDate);
        $this->assertInstanceOf(\DateTimeImmutable::class, $secondDate);
    }
}
