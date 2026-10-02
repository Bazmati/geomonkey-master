<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le champ payment_status à la table memberships
 * 
 * Ce champ permet de tracer le statut du paiement Stripe
 * (paid, failed, pending, etc.)
 */
final class Version20261002000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ payment_status à la table memberships';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE memberships ADD payment_status VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE memberships DROP COLUMN payment_status');
    }
}
