<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create order service tables: orders, order_line, outbox_event.';
    }

    public function up(Schema $schema): void
    {
        $orders = $schema->createTable('orders');
        $orders->addColumn('id', 'integer', ['autoincrement' => true]);
        $orders->addColumn('uuid', 'string', ['length' => 36]);
        $orders->addColumn('number', 'string', ['length' => 50]);
        $orders->addColumn('type', 'string', ['length' => 20]);
        $orders->addColumn('status', 'string', ['length' => 20]);
        $orders->addColumn('party_id', 'string', ['length' => 36]);
        $orders->addColumn('party_name', 'string', ['length' => 255]);
        $orders->addColumn('currency', 'string', ['length' => 3]);
        $orders->addColumn('version', 'integer', ['default' => 1]);
        $orders->addColumn('total_amount', 'integer', ['default' => 0]);
        $orders->addColumn('total_processed', 'integer', ['default' => 0]);
        $orders->addColumn('created_at', 'datetime', ['notnull' => false]);
        $orders->addColumn('updated_at', 'datetime', ['notnull' => false]);
        $orders->addColumn('submitted_at', 'datetime', ['notnull' => false]);
        $orders->addColumn('invoice_id', 'string', ['length' => 36, 'notnull' => false]);
        $orders->setPrimaryKey(['id']);
        $orders->addIndex(['number'], 'idx_order_number');
        $orders->addIndex(['status'], 'idx_order_status');

        $orderLine = $schema->createTable('order_line');
        $orderLine->addColumn('id', 'integer', ['autoincrement' => true]);
        $orderLine->addColumn('order_id', 'integer', ['notnull' => true]);
        $orderLine->addColumn('product_id', 'string', ['length' => 36]);
        $orderLine->addColumn('product_name', 'string', ['length' => 255]);
        $orderLine->addColumn('sku', 'string', ['length' => 50, 'notnull' => false]);
        $orderLine->addColumn('quantity', 'integer');
        $orderLine->addColumn('quantity_processed', 'integer', ['default' => 0]);
        $orderLine->addColumn('unit_price', 'integer');
        $orderLine->setPrimaryKey(['id']);
        $orderLine->addIndex(['product_id'], 'idx_order_line_product');
        $orderLine->addForeignKeyConstraint(
            'orders',
            ['order_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_order_line_order'
        );

        $outbox = $schema->createTable('outbox_event');
        $outbox->addColumn('id', 'integer', ['autoincrement' => true]);
        $outbox->addColumn('created_at', 'datetime');
        $outbox->addColumn('published_at', 'datetime', ['notnull' => false]);
        $outbox->addColumn('order_id', 'integer', ['notnull' => false]);
        $outbox->addColumn('event_type', 'string', ['length' => 100]);
        $outbox->addColumn('event_version', 'smallint');
        $outbox->addColumn('payload', 'json');
        $outbox->setPrimaryKey(['id']);
        $outbox->addIndex(['published_at'], 'idx_outbox_pending');
        $outbox->addForeignKeyConstraint(
            'orders',
            ['order_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            'fk_outbox_event_order'
        );
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('outbox_event');
        $schema->dropTable('order_line');
        $schema->dropTable('orders');
    }
}
