<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031005319 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_33401573DA4A75FF ON contacts');
        $this->addSql('ALTER TABLE contacts ADD blacklisted_by BINARY(16) DEFAULT NULL, ADD version INT DEFAULT 1 NOT NULL, DROP type');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contacts ADD type VARCHAR(255) NOT NULL, DROP blacklisted_by, DROP version');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_33401573DA4A75FF ON contacts (tax_number)');
    }
}
