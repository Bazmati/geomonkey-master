<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005140844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presentation_page ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE presentation_page ADD gallery_image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE presentation_page ALTER content SET NOT NULL');
        $this->addSql('ALTER TABLE presentation_page ALTER updated_at DROP NOT NULL');
        $this->addSql('ALTER TABLE presentation_page ADD CONSTRAINT FK_12AD166F828F7D6 FOREIGN KEY (gallery_image_id) REFERENCES gallery_image (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_12AD166F989D9B62 ON presentation_page (slug)');
        $this->addSql('CREATE INDEX IDX_12AD166F828F7D6 ON presentation_page (gallery_image_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE presentation_page DROP CONSTRAINT FK_12AD166F828F7D6');
        $this->addSql('DROP INDEX UNIQ_12AD166F989D9B62');
        $this->addSql('DROP INDEX IDX_12AD166F828F7D6');
        $this->addSql('ALTER TABLE presentation_page DROP created_at');
        $this->addSql('ALTER TABLE presentation_page DROP gallery_image_id');
        $this->addSql('ALTER TABLE presentation_page ALTER content DROP NOT NULL');
        $this->addSql('ALTER TABLE presentation_page ALTER updated_at SET NOT NULL');
    }
}
