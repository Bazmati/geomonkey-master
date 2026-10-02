<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour ajouter les champs RGPD à la table User
 * 
 * CHAMPS AJOUTÉS :
 * - terms_accepted_at : DateTimeImmutable (nullable) - Horodatage du consentement aux CGU
 * - image_consent : boolean - Consentement pour la publication des photos
 * - image_consent_at : DateTimeImmutable (nullable) - Horodatage du consentement image
 * - whatsapp_invite_sent : boolean - Invitation WhatsApp envoyée
 * 
 * Ces champs sont obligatoires pour la conformité RGPD lorsque l'on traite
 * des données d'adhérents payants.
 */
final class Version20261002000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs RGPD à la table User pour la conformité';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD terms_accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD image_consent BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE "user" ADD image_consent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD whatsapp_invite_sent BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP COLUMN terms_accepted_at');
        $this->addSql('ALTER TABLE "user" DROP COLUMN image_consent');
        $this->addSql('ALTER TABLE "user" DROP COLUMN image_consent_at');
        $this->addSql('ALTER TABLE "user" DROP COLUMN whatsapp_invite_sent');
    }
}
