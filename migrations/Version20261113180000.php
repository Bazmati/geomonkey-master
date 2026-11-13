<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le slug public des événements (URLs lisibles /evenements/mon-titre)
 * et remplit la colonne à partir des titres existants.
 */
final class Version20261113180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le slug des événements (URLs publiques lisibles) + backfill depuis les titres.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3BAE0AA7989D9B62 ON event (slug)');

        // Backfill : slug ASCII déduit du titre (équivalent SQL de computeSlug()).
        // translate() gère les accents français, regexp_replace le reste.
        $this->addSql(
            "UPDATE event SET slug = COALESCE(" .
            "  NULLIF(btrim(regexp_replace(lower(translate(title, " .
            "    'ÀÁÂÄÃÅàáâäãåÈÉÊËèéêëÌÍÎÏìíîïÒÓÔÖÕòóôöõÙÚÛÜùúûüÇçÑñŒœÆæ', " .
            "    'AAAAAAaaaaaaEEEEeeeeIIIIiiiiOOOOOoooooUUUUuuuuCcNnOoAa')), " .
            "    '[^a-z0-9]+', '-', 'g'), '-'), '')" .
            ", 'evenement')"
        );

        // Doublons (même titre) : on suffixe par l'id, de façon déterministe.
        $this->addSql(
            "UPDATE event e SET slug = e.slug || '-' || e.id " .
            "WHERE EXISTS (SELECT 1 FROM event e2 WHERE e2.slug = e.slug AND e2.id < e.id)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_3BAE0AA7989D9B62');
        $this->addSql('ALTER TABLE event DROP slug');
    }
}
