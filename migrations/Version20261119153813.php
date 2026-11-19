<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261119153813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE donations ADD fee_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE donations ADD net_amount INT DEFAULT NULL');
        $this->addSql('ALTER INDEX idx_event_category_id RENAME TO IDX_3BAE0AA712469DE2');
        $this->addSql('ALTER INDEX idx_event_event_tag_event RENAME TO IDX_94D34C6D71F7E88B');
        $this->addSql('ALTER INDEX idx_event_event_tag_tag RENAME TO IDX_94D34C6D884B1443');
        $this->addSql('ALTER INDEX uniq_event_category_name RENAME TO UNIQ_40A0F0115E237E06');
        $this->addSql('ALTER INDEX uniq_event_category_slug RENAME TO UNIQ_40A0F011989D9B62');
        $this->addSql('ALTER INDEX uniq_event_tag_name RENAME TO UNIQ_124672505E237E06');
        $this->addSql('ALTER INDEX uniq_event_tag_slug RENAME TO UNIQ_12467250989D9B62');
        $this->addSql('COMMENT ON COLUMN newsletter_subscriber.subscribed_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN newsletter_subscriber.unsubscribed_at IS \'\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE donations DROP fee_amount');
        $this->addSql('ALTER TABLE donations DROP net_amount');
        $this->addSql('ALTER INDEX idx_3bae0aa712469de2 RENAME TO idx_event_category_id');
        $this->addSql('ALTER INDEX uniq_40a0f011989d9b62 RENAME TO uniq_event_category_slug');
        $this->addSql('ALTER INDEX uniq_40a0f0115e237e06 RENAME TO uniq_event_category_name');
        $this->addSql('ALTER INDEX idx_94d34c6d71f7e88b RENAME TO idx_event_event_tag_event');
        $this->addSql('ALTER INDEX idx_94d34c6d884b1443 RENAME TO idx_event_event_tag_tag');
        $this->addSql('ALTER INDEX uniq_124672505e237e06 RENAME TO uniq_event_tag_name');
        $this->addSql('ALTER INDEX uniq_12467250989d9b62 RENAME TO uniq_event_tag_slug');
        $this->addSql('COMMENT ON COLUMN newsletter_subscriber.subscribed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN newsletter_subscriber.unsubscribed_at IS \'(DC2Type:datetime_immutable)\'');
    }
}
