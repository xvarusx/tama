<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115223930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema for Payment Service v1: Payment, PaymentPlan, Installment, PaymentAllocation, InvoiceReference, Reminder, Setting, OutboxEvent.';
    }

    public function up(Schema $schema): void
    {
        // Setting table - parameter table for app settings
        $setting = $schema->createTable('setting');
        $setting->addColumn('key', 'string', ['length' => 255]);
        $setting->addColumn('value', 'text');
        $setting->addColumn('type', 'string', ['length' => 50]);
        $setting->addColumn('description', 'text', ['notnull' => false]);
        $setting->setPrimaryKey(['key']);
        $setting->addUniqueIndex(['key'], 'UNI_D4E6F8945E237E06');
        $setting->addIndex(['type'], 'IDX_D4E6F8945E548F77');

        // InvoiceReference table - read model for invoices
        $invoiceRef = $schema->createTable('invoice_reference');
        $invoiceRef->addColumn('id', 'uuid', ['autoincrement' => false]);
        $invoiceRef->addColumn('invoice_id', 'uuid');
        $invoiceRef->addColumn('number', 'string', ['length' => 100]);
        $invoiceRef->addColumn('type', 'string', ['length' => 50]);
        $invoiceRef->addColumn('party_id', 'uuid');
        $invoiceRef->addColumn('party_name', 'string', ['length' => 255]);
        $invoiceRef->addColumn('currency', 'string', ['length' => 3]);
        $invoiceRef->addColumn('total', 'integer');
        $invoiceRef->addColumn('outstanding', 'integer');
        $invoiceRef->addColumn('status', 'string', ['length' => 50]);
        $invoiceRef->addColumn('created_at', 'datetime');
        $invoiceRef->addColumn('updated_at', 'datetime');
        $invoiceRef->setPrimaryKey(['id']);
        $invoiceRef->addUniqueIndex(['number'], 'UNIQ_9D73154D9D9F745D');
        $invoiceRef->addIndex(['party_id'], 'IDX_9D73154D7F89D77F');
        $invoiceRef->addIndex(['status'], 'IDX_9D73154DFCB5E734');

        // PaymentPlan table
        $paymentPlan = $schema->createTable('payment_plan');
        $paymentPlan->addColumn('id', 'uuid', ['autoincrement' => false]);
        $paymentPlan->addColumn('invoice_id', 'uuid');
        $paymentPlan->addColumn('total_amount', 'integer');
        $paymentPlan->addColumn('currency', 'string', ['length' => 3]);
        $paymentPlan->addColumn('status', 'string', ['length' => 50]);
        $paymentPlan->addColumn('version', 'integer', ['default' => 0]);
        $paymentPlan->addColumn('created_at', 'datetime');
        $paymentPlan->addColumn('updated_at', 'datetime');
        $paymentPlan->setPrimaryKey(['id']);
        $paymentPlan->addUniqueIndex(['invoice_id'], 'UNIQ_2E657A3D7E9F9D6D');
        $paymentPlan->addIndex(['status'], 'IDX_2E657A3DFCB5E734');

        // Installment table
        $installment = $schema->createTable('installment');
        $installment->addColumn('id', 'uuid', ['autoincrement' => false]);
        $installment->addColumn('payment_plan_id', 'uuid');
        $installment->addColumn('sequence', 'integer');
        $installment->addColumn('due_date', 'date');
        $installment->addColumn('amount', 'integer');
        $installment->addColumn('paid_amount', 'integer', ['default' => 0]);
        $installment->addColumn('status', 'string', ['length' => 50]);
        $installment->addColumn('created_at', 'datetime');
        $installment->addColumn('updated_at', 'datetime');
        $installment->setPrimaryKey(['id']);
        $installment->addIndex(['payment_plan_id'], 'IDX_2E657A3D8EEA267D');
        $installment->addIndex(['sequence'], 'IDX_2E657A3D95E72E94');
        $installment->addIndex(['status'], 'IDX_2E657A3DD6A2C65B');
        $installment->addIndex(['due_date'], 'IDX_2E657A3DC2628776');
        $installment->addForeignKeyConstraint('payment_plan', ['payment_plan_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Payment table
        $payment = $schema->createTable('payment');
        $payment->addColumn('id', 'uuid', ['autoincrement' => false]);
        $payment->addColumn('number', 'string', ['length' => 100]);
        $payment->addColumn('direction', 'string', ['length' => 20]);
        $payment->addColumn('method', 'string', ['length' => 20]);
        $payment->addColumn('status', 'string', ['length' => 50]);
        $payment->addColumn('party_id', 'uuid');
        $payment->addColumn('party_name', 'string', ['length' => 255]);
        $payment->addColumn('currency', 'string', ['length' => 3]);
        $payment->addColumn('amount', 'integer');
        $payment->addColumn('paid_at', 'datetime', ['notnull' => false]);
        $payment->addColumn('reference', 'string', ['length' => 255, 'notnull' => false]);
        $payment->addColumn('version', 'integer', ['default' => 0]);
        $payment->addColumn('created_at', 'datetime');
        $payment->addColumn('updated_at', 'datetime');
        $payment->setPrimaryKey(['id']);
        $payment->addUniqueIndex(['number'], 'UNIQ_E8EC2A3D8E8B9E5D');
        $payment->addIndex(['status'], 'IDX_E8EC2A3DFCB5E734');
        $payment->addIndex(['direction'], 'IDX_E8EC2A3DD4831442');
        $payment->addIndex(['party_id'], 'IDX_E8EC2A3D2F74F970');
        $payment->addIndex(['method'], 'IDX_E8EC2A3D5B6BB01A');
        $payment->addIndex(['paid_at'], 'IDX_E8EC2A3D927F777B');

        // PaymentAllocation table
        $allocation = $schema->createTable('payment_allocation');
        $allocation->addColumn('id', 'uuid', ['autoincrement' => false]);
        $allocation->addColumn('payment_id', 'uuid');
        $allocation->addColumn('installment_id', 'uuid');
        $allocation->addColumn('invoice_id', 'uuid');
        $allocation->addColumn('amount', 'integer');
        $allocation->addColumn('created_at', 'datetime');
        $allocation->setPrimaryKey(['id']);
        $allocation->addIndex(['payment_id'], 'IDX_7DEC8579E8EC2A3D');
        $allocation->addIndex(['installment_id'], 'IDX_7DEC8579A9E9F92F');
        $allocation->addIndex(['invoice_id'], 'IDX_7DEC85791956595E');
        $allocation->addForeignKeyConstraint('payment', ['payment_id'], ['id'], ['onDelete' => 'CASCADE']);
        $allocation->addForeignKeyConstraint('installment', ['installment_id'], ['id'], ['onDelete' => 'CASCADE']);
        $allocation->addForeignKeyConstraint('invoice_reference', ['invoice_id'], ['id'], ['onDelete' => 'CASCADE']);

        // Reminder table
        $reminder = $schema->createTable('reminder');
        $reminder->addColumn('id', 'uuid', ['autoincrement' => false]);
        $reminder->addColumn('installment_id', 'uuid');
        $reminder->addColumn('kind', 'string', ['length' => 50]);
        $reminder->addColumn('scheduled_for', 'date');
        $reminder->addColumn('status', 'string', ['length' => 50]);
        $reminder->addColumn('sent_at', 'datetime', ['notnull' => false]);
        $reminder->addColumn('created_at', 'datetime');
        $reminder->addColumn('updated_at', 'datetime');
        $reminder->setPrimaryKey(['id']);
        $reminder->addIndex(['installment_id'], 'IDX_678B9E7DA0E8B787');
        $reminder->addIndex(['kind'], 'IDX_678B9E7D39A84C68');
        $reminder->addIndex(['scheduled_for'], 'IDX_678B9E7D5E69E59B');
        $reminder->addIndex(['status'], 'IDX_678B9E7D74037791');
        $reminder->addForeignKeyConstraint('installment', ['installment_id'], ['id'], ['onDelete' => 'CASCADE']);

        // OutboxEvent table
        $outbox = $schema->createTable('outbox_event');
        $outbox->addColumn('id', 'integer', ['autoincrement' => true]);
        $outbox->addColumn('event_type', 'string', ['length' => 100]);
        $outbox->addColumn('event_version', 'smallint');
        $outbox->addColumn('payload', 'json');
        $outbox->addColumn('created_at', 'datetime');
        $outbox->addColumn('published_at', 'datetime', ['notnull' => false]);
        $outbox->setPrimaryKey(['id']);
        $outbox->addIndex(['published_at'], 'idx_outbox_pending');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('outbox_event');
        $schema->dropTable('reminder');
        $schema->dropTable('payment_allocation');
        $schema->dropTable('payment');
        $schema->dropTable('installment');
        $schema->dropTable('payment_plan');
        $schema->dropTable('invoice_reference');
        $schema->dropTable('setting');
    }
}