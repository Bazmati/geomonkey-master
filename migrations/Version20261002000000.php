<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration de rattrapage : Crée des memberships pour les membres existants
 * 
 * PROBLÈME : La migration précédente a ajouté is_registration_confirmed DEFAULT false
 * et les membres existants n'ont aucune ligne dans la table memberships.
 * 
 * Conséquence : dès l'activation du cron app:membership:expire,
 * hasActiveMembership() === false → applyMembershipStatus() → 
 * tous les membres du bureau et actifs passent à OfficeFunction::None.
 * 
 * SOLUTION : Créer une membership par membre actif existant avec:
 * - is_active: true
 * - started_at: il y a 1 an (pour éviter l'expiration immédiate)
 * - expires_at: maintenant (afin que le cron ne les expire pas tout de suite)
 * - amount: 0 (paiement historique non tracé par Stripe)
 * - stripe_payment_id: null
 * 
 * ET mettre à jour les users concernés:
 * - is_registration_confirmed: true
 * - registration_validated_at: maintenant
 */
final class Version20261002000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration de rattrapage : crée des memberships pour les membres existants et valide leur inscription';
    }

    public function up(Schema $schema): void
    {
        // Créer une membership pour chaque utilisateur avec une fonction de bureau
        // qui n'a pas déjà de membership active
        $this->addSql(
            'INSERT INTO memberships (user_id, is_active, started_at, expires_at, amount, stripe_payment_id, renewed_at, reminder_sent_at) '
            . 'SELECT u.id, true, CURRENT_DATE - INTERVAL \'1 year\', CURRENT_DATE + INTERVAL \'1 year\', 0, null, null, null '
            . 'FROM "user" u '
            . 'WHERE u.office_function IN (\'president\', \'tresorier\', \'secretaire\', \'membre_actif\') '
            . 'AND NOT EXISTS (SELECT 1 FROM memberships m WHERE m.user_id = u.id AND m.is_active = true AND m.expires_at >= CURRENT_DATE)'
        );

        // Mettre à jour les utilisateurs concernés pour marquer leur inscription comme validée
        $this->addSql(
            'UPDATE "user" SET is_registration_confirmed = true, registration_validated_at = NOW() '
            . 'WHERE office_function IN (\'president\', \'tresorier\', \'secretaire\', \'membre_actif\')'
        );
    }

    public function down(Schema $schema): void
    {
        // En rollback, on supprime les memberships créées par cette migration
        // et on remet les champs à false/null pour les utilisateurs concernés
        $this->addSql(
            'DELETE FROM memberships '
            . 'WHERE user_id IN (SELECT u.id FROM "user" u WHERE u.office_function IN (\'president\', \'tresorier\', \'secretaire\', \'membre_actif\')) '
            . 'AND amount = 0 AND stripe_payment_id IS NULL'
        );

        $this->addSql(
            'UPDATE "user" SET is_registration_confirmed = false, registration_validated_at = NULL '
            . 'WHERE office_function IN (\'president\', \'tresorier\', \'secretaire\', \'membre_actif\')'
        );
    }
}