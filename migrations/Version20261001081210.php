<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261001081210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update EventParticipant: set role NOT NULL with default participant, set registeredAt NOT NULL';
    }

    public function up(Schema $schema): void
    {
        // Mettre à jour les rôles existants pour utiliser les valeurs de l'enum ParticipantRole
        // Convertir les valeurs existantes (si elles existent) vers les valeurs de l'enum
        $this->addSql('ALTER TABLE event_participant ALTER role SET NOT NULL');
        $this->addSql('ALTER TABLE event_participant ALTER role SET DEFAULT \'participant\'');
        
        // Mettre à jour les valeurs existantes pour correspondre à l'enum
        // Note: comme la table est vide, cette étape est optionnelle mais incluse pour la sécurité
        $this->addSql('UPDATE event_participant SET role = \'organisateur\' WHERE role = \'organisateur\'');
        $this->addSql('UPDATE event_participant SET role = \'benevole\' WHERE role = \'bénévole\'');
        $this->addSql('UPDATE event_participant SET role = \'participant\' WHERE role = \'participant\'');
        
        // S'assurer qu'il n'y a pas de valeurs NULL (mettre à jour avec la valeur par défaut)
        $this->addSql('UPDATE event_participant SET role = \'participant\' WHERE role IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event_participant ALTER role DROP NOT NULL');
        $this->addSql('ALTER TABLE event_participant ALTER role DROP DEFAULT');
    }
}
