<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correct the contact-service schema after the UUID migration.
 *
 * Historical migrations left contacts.id, contact_audit_entries.id/contact_id and
 * outbox_event.id as BINARY(16)/INT while the entities now use Doctrine's 'uuid'
 * type (CHAR(36) on MySQL). The legacy client table and the trust_level column
 * are no longer mapped and must go as well.
 */
final class Version20261118110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align contact, audit and outbox identifiers with the UUID mapping and drop legacy client/trust_level schema.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS client');
        $this->addSql('DROP INDEX idx_contact_trust_level ON contacts');
        $this->addSql('ALTER TABLE contacts DROP trust_level, MODIFY id BINARY(16) NOT NULL, MODIFY created_by BINARY(16) DEFAULT NULL, MODIFY updated_by BINARY(16) DEFAULT NULL, MODIFY blacklisted_by BINARY(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_audit_entries MODIFY id BINARY(16) NOT NULL, MODIFY contact_id BINARY(16) NOT NULL, MODIFY user_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE outbox_event MODIFY id BINARY(16) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Best-effort reversal for local development; previously stored UUIDs are not
        // convertible back to the old binary/int identifiers.
        $this->addSql('ALTER TABLE outbox_event MODIFY id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE contact_audit_entries MODIFY user_id INT NOT NULL, MODIFY contact_id BINARY(16) NOT NULL, MODIFY id BINARY(16) NOT NULL');
        $this->addSql("ALTER TABLE contacts MODIFY blacklisted_by BINARY(16) DEFAULT NULL, MODIFY updated_by INT DEFAULT NULL, MODIFY created_by INT DEFAULT NULL, MODIFY id BINARY(16) NOT NULL, ADD trust_level VARCHAR(16) DEFAULT '' NOT NULL");
        $this->addSql('CREATE INDEX idx_contact_trust_level ON contacts (trust_level)');
        $this->addSql('CREATE TABLE IF NOT EXISTS client (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, type VARCHAR(255) NOT NULL, cif_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}
