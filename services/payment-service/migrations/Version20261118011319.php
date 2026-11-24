<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261118011319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename the reserved-word setting.key column to setting_key.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_setting_key ON setting');
        $this->addSql('ALTER TABLE setting CHANGE `key` setting_key VARCHAR(255) NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (setting_key)');
        $this->addSql('CREATE INDEX idx_setting_key ON setting (setting_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_setting_key ON setting');
        $this->addSql('ALTER TABLE setting CHANGE setting_key `key` VARCHAR(255) NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (key)');
        $this->addSql('CREATE INDEX idx_setting_key ON setting (`key`)');
    }
}
