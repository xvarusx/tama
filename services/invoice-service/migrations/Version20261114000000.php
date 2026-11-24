<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoice tables: invoices, lines, credit notes, payments, outbox.';
    }

    public function up(Schema $schema): void
    {
        $invoices = $schema->createTable('invoices');
        $invoices->addColumn('id', 'uuid');
        $invoices->addColumn('number', 'string', ['length' => 100, 'notnull' => false]);
        $invoices->addColumn('type', 'string', ['length' => 20]);
        $invoices->addColumn('status', 'string', ['length' => 20]);
        $invoices->addColumn('order_id', 'uuid');
        $invoices->addColumn('active_order_key', 'string', ['length' => 36, 'notnull' => false]);
        $invoices->addColumn('party_id', 'uuid');
        $invoices->addColumn('party_name', 'string', ['length' => 255]);
        $invoices->addColumn('currency', 'string', ['length' => 3]);
        $invoices->addColumn('issue_date', 'datetime_immutable', ['notnull' => false]);
        $invoices->addColumn('due_date', 'datetime_immutable', ['notnull' => false]);
        $invoices->addColumn('amount_paid', 'integer');
        $invoices->addColumn('overpayment_flagged', 'boolean');
        $invoices->addColumn('version', 'integer', ['default' => 1]);
        $invoices->addColumn('created_at', 'datetime_immutable');
        $invoices->addColumn('updated_at', 'datetime_immutable');
        $invoices->setPrimaryKey(['id']);
        $invoices->addUniqueIndex(['number'], 'uniq_invoices_number');
        $invoices->addUniqueIndex(['active_order_key'], 'uniq_invoices_active_order_key');
        $invoices->addIndex(['order_id'], 'idx_invoices_order');
        $invoices->addIndex(['status'], 'idx_invoices_status');
        $invoices->addIndex(['type'], 'idx_invoices_type');

        $lines = $schema->createTable('invoice_lines');
        $lines->addColumn('id', 'integer', ['autoincrement' => true]);
        $lines->addColumn('invoice_id', 'uuid');
        $lines->addColumn('product_id', 'uuid');
        $lines->addColumn('description', 'string', ['length' => 255]);
        $lines->addColumn('quantity', 'integer');
        $lines->addColumn('unit_price', 'integer');
        $lines->addColumn('tax_rate_bps', 'smallint');
        $lines->addColumn('tax_amount', 'integer');
        $lines->addColumn('position', 'smallint');
        $lines->setPrimaryKey(['id']);
        $lines->addIndex(['invoice_id'], 'idx_invoice_lines_invoice');
        $lines->addForeignKeyConstraint('invoices', ['invoice_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_invoice_lines_invoice');

        $creditNotes = $schema->createTable('credit_notes');
        $creditNotes->addColumn('id', 'integer', ['autoincrement' => true]);
        $creditNotes->addColumn('invoice_id', 'uuid');
        $creditNotes->addColumn('number', 'string', ['length' => 100]);
        $creditNotes->addColumn('amount', 'integer');
        $creditNotes->addColumn('reason', 'string', ['length' => 255]);
        $creditNotes->addColumn('issued_at', 'datetime_immutable');
        $creditNotes->setPrimaryKey(['id']);
        $creditNotes->addUniqueIndex(['number'], 'uniq_credit_notes_number');
        $creditNotes->addIndex(['invoice_id'], 'idx_credit_notes_invoice');
        $creditNotes->addForeignKeyConstraint('invoices', ['invoice_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_credit_notes_invoice');

        $payments = $schema->createTable('invoice_payments');
        $payments->addColumn('id', 'integer', ['autoincrement' => true]);
        $payments->addColumn('invoice_id', 'uuid');
        $payments->addColumn('payment_id', 'uuid');
        $payments->addColumn('amount', 'integer');
        $payments->addColumn('currency', 'string', ['length' => 3]);
        $payments->addColumn('accepted', 'boolean');
        $payments->addColumn('received_at', 'datetime_immutable');
        $payments->setPrimaryKey(['id']);
        $payments->addUniqueIndex(['payment_id'], 'uniq_invoice_payments_payment_id');
        $payments->addIndex(['invoice_id'], 'idx_invoice_payments_invoice');
        $payments->addForeignKeyConstraint('invoices', ['invoice_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_invoice_payments_invoice');

        $outbox = $schema->createTable('outbox_events');
        $outbox->addColumn('id', 'integer', ['autoincrement' => true]);
        $outbox->addColumn('event_type', 'string', ['length' => 100]);
        $outbox->addColumn('event_version', 'smallint');
        $outbox->addColumn('payload', 'json');
        $outbox->addColumn('created_at', 'datetime_immutable');
        $outbox->addColumn('published_at', 'datetime_immutable', ['notnull' => false]);
        $outbox->setPrimaryKey(['id']);
        $outbox->addIndex(['published_at'], 'idx_outbox_pending');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('outbox_events');
        $schema->dropTable('invoice_payments');
        $schema->dropTable('credit_notes');
        $schema->dropTable('invoice_lines');
        $schema->dropTable('invoices');
    }
}
