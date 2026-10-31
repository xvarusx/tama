<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store authentication-service user IDs and align contact indexes with the entity mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contacts MODIFY blacklisted_by INT DEFAULT NULL, ADD created_by INT DEFAULT NULL, ADD updated_by INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_contact_tax_number_type ON contacts (tax_number, contact_type)');
        $this->addSql('CREATE INDEX idx_contact_type ON contacts (contact_type)');
        $this->addSql('CREATE INDEX idx_contact_status ON contacts (status)');
        $this->addSql('CREATE INDEX idx_contact_trust_level ON contacts (trust_level)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_contact_tax_number_type ON contacts');
        $this->addSql('DROP INDEX idx_contact_type ON contacts');
        $this->addSql('DROP INDEX idx_contact_status ON contacts');
        $this->addSql('DROP INDEX idx_contact_trust_level ON contacts');
        $this->addSql('ALTER TABLE contacts MODIFY blacklisted_by BINARY(16) DEFAULT NULL, DROP created_by, DROP updated_by');
    }
}
