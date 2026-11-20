<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns the payment-service schema with the entity mappings after the
 * int-to-UUID migration: UUID primary/foreign keys stay BINARY(16) (DBAL uuid
 * type), while external string references (party_id, invoice_id) become
 * VARCHAR(36). Stale legacy columns/index names are corrected as well.
 */
final class Version20261118011141 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align payment-service UUID columns with the DBAL uuid type (BINARY(16) ids, VARCHAR(36) external references) and drop stale legacy columns.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE installment DROP FOREIGN KEY `FK_4B778ACDF1D00C71`');
        $this->addSql('ALTER TABLE installment DROP created_at, DROP updated_at, CHANGE paid_amount paid_amount INT NOT NULL, CHANGE status status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE installment ADD CONSTRAINT FK_4B778ACDF1D00C71 FOREIGN KEY (payment_plan_id) REFERENCES payment_plan (id)');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_2e657a3d8eea267d TO idx_installment_plan');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_2e657a3d95e72e94 TO idx_installment_sequence');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_2e657a3dd6a2c65b TO idx_installment_status');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_2e657a3dc2628776 TO idx_installment_due_date');
        $this->addSql('ALTER TABLE invoice_reference CHANGE invoice_id invoice_id VARCHAR(36) NOT NULL, CHANGE party_id party_id VARCHAR(36) NOT NULL');
        $this->addSql('CREATE INDEX idx_invoice_ref_number ON invoice_reference (number)');
        $this->addSql('ALTER TABLE invoice_reference RENAME INDEX uniq_9d73154d9d9f745d TO UNIQ_CB0CB88F96901F54');
        $this->addSql('ALTER TABLE invoice_reference RENAME INDEX idx_9d73154d7f89d77f TO idx_invoice_ref_party');
        $this->addSql('ALTER TABLE invoice_reference RENAME INDEX idx_9d73154dfcb5e734 TO idx_invoice_ref_status');
        $this->addSql('ALTER TABLE payment DROP updated_at, CHANGE direction direction VARCHAR(255) NOT NULL, CHANGE method method VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE party_id party_id VARCHAR(36) NOT NULL, CHANGE paid_at paid_at DATE DEFAULT NULL, CHANGE version version INT NOT NULL');
        $this->addSql('ALTER TABLE payment RENAME INDEX uniq_e8ec2a3d8e8b9e5d TO UNIQ_6D28840D96901F54');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_e8ec2a3dfcb5e734 TO idx_payment_status');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_e8ec2a3dd4831442 TO idx_payment_direction');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_e8ec2a3d2f74f970 TO idx_payment_party');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_e8ec2a3d5b6bb01a TO idx_payment_method');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_e8ec2a3d927f777b TO idx_payment_paid_at');
        $this->addSql('ALTER TABLE payment_allocation DROP FOREIGN KEY `FK_69B2A2FF2989F1FD`');
        $this->addSql('ALTER TABLE payment_allocation DROP FOREIGN KEY `FK_69B2A2FF4C3A3BB`');
        $this->addSql('ALTER TABLE payment_allocation DROP FOREIGN KEY `FK_69B2A2FFF03B5436`');
        $this->addSql('ALTER TABLE payment_allocation DROP created_at');
        $this->addSql('ALTER TABLE payment_allocation ADD CONSTRAINT FK_69B2A2FF2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice_reference (id)');
        $this->addSql('ALTER TABLE payment_allocation ADD CONSTRAINT FK_69B2A2FF4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');
        $this->addSql('ALTER TABLE payment_allocation ADD CONSTRAINT FK_69B2A2FFF03B5436 FOREIGN KEY (installment_id) REFERENCES installment (id)');
        $this->addSql('ALTER TABLE payment_allocation RENAME INDEX idx_7dec8579a9e9f92f TO idx_allocation_installment');
        $this->addSql('ALTER TABLE payment_allocation RENAME INDEX idx_7dec85791956595e TO idx_allocation_invoice');
        $this->addSql('ALTER TABLE payment_allocation RENAME INDEX idx_7dec8579e8ec2a3d TO idx_allocation_payment');
        $this->addSql('DROP INDEX UNIQ_2E657A3D7E9F9D6D ON payment_plan');
        $this->addSql('ALTER TABLE payment_plan DROP updated_at, CHANGE invoice_id invoice_id VARCHAR(36) NOT NULL, CHANGE version version INT NOT NULL');
        $this->addSql('ALTER TABLE payment_plan RENAME INDEX idx_2e657a3dfcb5e734 TO idx_payment_plan_status');
        $this->addSql('ALTER TABLE reminder DROP FOREIGN KEY `FK_40374F40F03B5436`');
        $this->addSql('ALTER TABLE reminder DROP created_at, DROP updated_at, CHANGE kind kind VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reminder ADD CONSTRAINT FK_40374F40F03B5436 FOREIGN KEY (installment_id) REFERENCES installment (id)');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_678b9e7d39a84c68 TO idx_reminder_kind');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_678b9e7d5e69e59b TO idx_reminder_scheduled_for');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_678b9e7d74037791 TO idx_reminder_status');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_678b9e7da0e8b787 TO IDX_40374F40F03B5436');
        $this->addSql('ALTER TABLE setting DROP INDEX UNI_D4E6F8945E237E06, ADD INDEX idx_setting_key (`key`)');
        $this->addSql('ALTER TABLE setting RENAME INDEX idx_d4e6f8945e548f77 TO idx_setting_type');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE installment DROP FOREIGN KEY FK_4B778ACDF1D00C71');
        $this->addSql('ALTER TABLE installment ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, CHANGE paid_amount paid_amount INT DEFAULT 0 NOT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE installment ADD CONSTRAINT `FK_4B778ACDF1D00C71` FOREIGN KEY (payment_plan_id) REFERENCES payment_plan (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_installment_plan TO IDX_2E657A3D8EEA267D');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_installment_sequence TO IDX_2E657A3D95E72E94');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_installment_due_date TO IDX_2E657A3DC2628776');
        $this->addSql('ALTER TABLE installment RENAME INDEX idx_installment_status TO IDX_2E657A3DD6A2C65B');
        $this->addSql('DROP INDEX idx_invoice_ref_number ON invoice_reference');
        $this->addSql('ALTER TABLE invoice_reference CHANGE invoice_id invoice_id BINARY(16) NOT NULL, CHANGE party_id party_id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE invoice_reference RENAME INDEX idx_invoice_ref_party TO IDX_9D73154D7F89D77F');
        $this->addSql('ALTER TABLE invoice_reference RENAME INDEX idx_invoice_ref_status TO IDX_9D73154DFCB5E734');
        $this->addSql('ALTER TABLE invoice_reference RENAME INDEX uniq_cb0cb88f96901f54 TO UNIQ_9D73154D9D9F745D');
        $this->addSql('ALTER TABLE payment ADD updated_at DATETIME NOT NULL, CHANGE direction direction VARCHAR(20) NOT NULL, CHANGE method method VARCHAR(20) NOT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE party_id party_id BINARY(16) NOT NULL, CHANGE paid_at paid_at DATETIME DEFAULT NULL, CHANGE version version INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_payment_party TO IDX_E8EC2A3D2F74F970');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_payment_method TO IDX_E8EC2A3D5B6BB01A');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_payment_paid_at TO IDX_E8EC2A3D927F777B');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_payment_direction TO IDX_E8EC2A3DD4831442');
        $this->addSql('ALTER TABLE payment RENAME INDEX idx_payment_status TO IDX_E8EC2A3DFCB5E734');
        $this->addSql('ALTER TABLE payment RENAME INDEX uniq_6d28840d96901f54 TO UNIQ_E8EC2A3D8E8B9E5D');
        $this->addSql('ALTER TABLE payment_allocation DROP FOREIGN KEY FK_69B2A2FF4C3A3BB');
        $this->addSql('ALTER TABLE payment_allocation DROP FOREIGN KEY FK_69B2A2FFF03B5436');
        $this->addSql('ALTER TABLE payment_allocation DROP FOREIGN KEY FK_69B2A2FF2989F1FD');
        $this->addSql('ALTER TABLE payment_allocation ADD created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE payment_allocation ADD CONSTRAINT `FK_69B2A2FF4C3A3BB` FOREIGN KEY (payment_id) REFERENCES payment (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_allocation ADD CONSTRAINT `FK_69B2A2FFF03B5436` FOREIGN KEY (installment_id) REFERENCES installment (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_allocation ADD CONSTRAINT `FK_69B2A2FF2989F1FD` FOREIGN KEY (invoice_id) REFERENCES invoice_reference (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payment_allocation RENAME INDEX idx_allocation_invoice TO IDX_7DEC85791956595E');
        $this->addSql('ALTER TABLE payment_allocation RENAME INDEX idx_allocation_installment TO IDX_7DEC8579A9E9F92F');
        $this->addSql('ALTER TABLE payment_allocation RENAME INDEX idx_allocation_payment TO IDX_7DEC8579E8EC2A3D');
        $this->addSql('ALTER TABLE payment_plan ADD updated_at DATETIME NOT NULL, CHANGE invoice_id invoice_id BINARY(16) NOT NULL, CHANGE version version INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2E657A3D7E9F9D6D ON payment_plan (invoice_id)');
        $this->addSql('ALTER TABLE payment_plan RENAME INDEX idx_payment_plan_status TO IDX_2E657A3DFCB5E734');
        $this->addSql('ALTER TABLE reminder DROP FOREIGN KEY FK_40374F40F03B5436');
        $this->addSql('ALTER TABLE reminder ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, CHANGE kind kind VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE reminder ADD CONSTRAINT `FK_40374F40F03B5436` FOREIGN KEY (installment_id) REFERENCES installment (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_reminder_kind TO IDX_678B9E7D39A84C68');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_reminder_scheduled_for TO IDX_678B9E7D5E69E59B');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_reminder_status TO IDX_678B9E7D74037791');
        $this->addSql('ALTER TABLE reminder RENAME INDEX idx_40374f40f03b5436 TO IDX_678B9E7DA0E8B787');
        $this->addSql('ALTER TABLE setting DROP INDEX idx_setting_key, ADD UNIQUE INDEX UNI_D4E6F8945E237E06 (`key`)');
        $this->addSql('ALTER TABLE setting RENAME INDEX idx_setting_type TO IDX_D4E6F8945E548F77');
    }
}
