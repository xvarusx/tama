<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correct the order-service primary keys from INT AUTO_INCREMENT to UUID (DBAL
 * "uuid" type). Destructive table-recreate: dev fixtures are reloaded afterwards.
 */
final class Version20261118130000 extends AbstractMigration
{
    private const array TABLES = ['outbox_event', 'order_line', 'orders'];

    public function getDescription(): string
    {
        return 'Migrate order-service ids and order_id FKs from INT AUTO_INCREMENT to UUID (dev fixtures are reloaded).';
    }

    public function up(Schema $schema): void
    {
        $this->dropTables();
        foreach ($this->buildSchema(true)->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->dropTables();
        foreach ($this->buildSchema(false)->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->addSql($sql);
        }
    }

    private function dropTables(): void
    {
        // Drop children first; wrap with FK checks off on MySQL.
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('DROP TABLE IF EXISTS %s', $table));
        }
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    private function buildSchema(bool $uuid): Schema
    {
        $schema = new Schema();
        $idType = $uuid ? 'uuid' : 'integer';
        $idOpts = $uuid ? ['autoincrement' => false] : ['autoincrement' => true];

        $orders = $schema->createTable('orders');
        $orders->addColumn('id', $idType, $idOpts);
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
        $orders->addColumn('invoice_id', 'uuid', ['notnull' => false]);
        $orders->setPrimaryKey(['id']);
        $orders->addIndex(['number'], 'idx_order_number');
        $orders->addIndex(['status'], 'idx_order_status');

        $orderLine = $schema->createTable('order_line');
        $orderLine->addColumn('id', $idType, $idOpts);
        $orderLine->addColumn('order_id', $idType, $uuid ? ['notnull' => true] : ['notnull' => true]);
        $orderLine->addColumn('product_id', 'uuid', ['autoincrement' => false]);
        $orderLine->addColumn('product_name', 'string', ['length' => 255]);
        $orderLine->addColumn('sku', 'string', ['length' => 50, 'notnull' => false]);
        $orderLine->addColumn('quantity', 'integer');
        $orderLine->addColumn('quantity_processed', 'integer', ['default' => 0]);
        $orderLine->addColumn('unit_price', 'integer');
        $orderLine->setPrimaryKey(['id']);
        $orderLine->addIndex(['product_id'], 'idx_order_line_product');
        $orderLine->addForeignKeyConstraint('orders', ['order_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_order_line_order');

        $outbox = $schema->createTable('outbox_event');
        $outbox->addColumn('id', $idType, $idOpts);
        $outbox->addColumn('created_at', 'datetime');
        $outbox->addColumn('published_at', 'datetime', ['notnull' => false]);
        $outbox->addColumn('order_id', $idType, ['notnull' => false]);
        $outbox->addColumn('event_type', 'string', ['length' => 100]);
        $outbox->addColumn('event_version', 'smallint');
        $outbox->addColumn('payload', 'json');
        $outbox->setPrimaryKey(['id']);
        $outbox->addIndex(['published_at'], 'idx_outbox_pending');
        $outbox->addForeignKeyConstraint('orders', ['order_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_outbox_event_order');

        return $schema;
    }
}
