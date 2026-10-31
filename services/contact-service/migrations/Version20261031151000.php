<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031151000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist contact change actors and actions, including contact deletions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_audit_entries (id BINARY(16) NOT NULL, contact_id BINARY(16) NOT NULL, user_id INT NOT NULL, action VARCHAR(32) NOT NULL, occurred_at DATETIME NOT NULL, INDEX idx_contact_audit_contact (contact_id), INDEX idx_contact_audit_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_audit_entries');
    }
}
