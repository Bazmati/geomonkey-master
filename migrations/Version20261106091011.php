<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106091011 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" ADD email_verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD email_verification_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD password_reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP email_verification_token');
        $this->addSql('ALTER TABLE "user" DROP email_verification_token_expires_at');
        $this->addSql('ALTER TABLE "user" DROP password_reset_token');
        $this->addSql('ALTER TABLE "user" DROP password_reset_token_expires_at');
    }
}
