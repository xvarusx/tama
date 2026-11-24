<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correct all product-service primary keys and foreign keys from auto-increment
 * integers to UUIDs (DBAL "uuid" type), following the payment/invoice pattern.
 *
 * This is a destructive, table-recreate migration: the seven tables are dropped
 * and rebuilt with the same non-id columns. Development data is intentionally
 * not preserved; reload it with `bin/console doctrine:fixtures:load --no-interaction`.
 */
final class Version20261118000000 extends AbstractMigration
{
    private const array TABLES = [
        'product_media',
        'product_variant',
        'product',
        'unit',
        'category',
        'brand',
        'outbox_event',
    ];

    public function getDescription(): string
    {
        return 'Migrate all product-service entity ids and reference ids from INT AUTO_INCREMENT to UUID by recreating the tables (dev fixtures are reloaded).';
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
        // Best effort: restore the original integer-id schema (data is not preserved).
        $this->dropTables();

        foreach ($this->buildSchema(false)->toSql($this->connection->getDatabasePlatform()) as $sql) {
            $this->addSql($sql);
        }
    }

    /** Drop children before parents so foreign keys never block the drop. */
    private function dropTables(): void
    {
        foreach (self::TABLES as $table) {
            $this->addSql(sprintf('DROP TABLE %s', $table));
        }
    }

    /**
     * Build the full schema with the Schema API so the generated CREATE TABLE
     * statements stay portable across MySQL and SQLite.
     */
    private function buildSchema(bool $uuid): Schema
    {
        $idType = $uuid ? 'uuid' : 'integer';
        $idOptions = $uuid ? ['autoincrement' => false] : ['autoincrement' => true];
        $fkOptions = $uuid ? ['autoincrement' => false] : [];

        $schema = new Schema();

        $brand = $schema->createTable('brand');
        $brand->addColumn('id', $idType, $idOptions);
        $brand->addColumn('name', 'string', ['length' => 255]);
        $brand->addColumn('slug', 'string', ['length' => 255]);
        $brand->addColumn('created_at', 'datetime');
        $brand->addColumn('updated_at', 'datetime');
        $brand->setPrimaryKey(['id']);
        $brand->addUniqueIndex(['name'], 'UNIQ_1C52F9585E237E06');
        $brand->addUniqueIndex(['slug'], 'UNIQ_1C52F958989D9B62');

        $category = $schema->createTable('category');
        $category->addColumn('id', $idType, $idOptions);
        $category->addColumn('name', 'string', ['length' => 255]);
        $category->addColumn('slug', 'string', ['length' => 255]);
        $category->addColumn('position', 'integer', ['default' => 0]);
        $category->addColumn('created_at', 'datetime');
        $category->addColumn('updated_at', 'datetime');
        $category->setPrimaryKey(['id']);
        $category->addUniqueIndex(['slug'], 'UNIQ_64C19C1989D9B62');

        $unit = $schema->createTable('unit');
        $unit->addColumn('id', $idType, $idOptions);
        $unit->addColumn('code', 'string', ['length' => 55]);
        $unit->addColumn('allow_decimal', 'boolean');
        $unit->addColumn('created_at', 'datetime');
        $unit->addColumn('updated_at', 'datetime');
        $unit->setPrimaryKey(['id']);
        $unit->addUniqueIndex(['code'], 'UNIQ_DCBB0C5377153098');

        $product = $schema->createTable('product');
        $product->addColumn('id', $idType, $idOptions);
        $product->addColumn('brand_id', $idType, [...$fkOptions, 'notnull' => false]);
        $product->addColumn('unit_id', $idType, [...$fkOptions, 'notnull' => false]);
        $product->addColumn('category_id', $idType, [...$fkOptions, 'notnull' => false]);
        $product->addColumn('name', 'string', ['length' => 255]);
        $product->addColumn('description', 'text', ['notnull' => false]);
        $product->addColumn('version', 'integer', ['default' => 1]);
        $product->addColumn('created_at', 'datetime');
        $product->addColumn('updated_at', 'datetime');
        $product->setPrimaryKey(['id']);
        $product->addIndex(['brand_id'], 'IDX_D34A04AD44F5D008');
        $product->addIndex(['unit_id'], 'IDX_D34A04ADF8BD700D');
        $product->addIndex(['category_id'], 'IDX_D34A04AD12469DE2');

        $variant = $schema->createTable('product_variant');
        $variant->addColumn('id', $idType, $idOptions);
        $variant->addColumn('product_id', $idType, $fkOptions);
        $variant->addColumn('sku', 'string', ['length' => 255, 'notnull' => false]);
        $variant->addColumn('barcode', 'string', ['length' => 255, 'notnull' => false]);
        $variant->addColumn('options', 'json', ['notnull' => false]);
        $variant->addColumn('cost_price', 'decimal', ['precision' => 12, 'scale' => 2]);
        $variant->addColumn('selling_price', 'decimal', ['precision' => 12, 'scale' => 2]);
        $variant->addColumn('stock_updated_at', 'datetime', ['notnull' => false]);
        $variant->addColumn('created_at', 'datetime');
        $variant->addColumn('updated_at', 'datetime');
        $variant->setPrimaryKey(['id']);
        $variant->addUniqueIndex(['sku'], 'UNIQ_209AA41DF9038C4');
        $variant->addIndex(['product_id'], 'IDX_209AA41D4584665A');

        $media = $schema->createTable('product_media');
        $media->addColumn('id', $idType, $idOptions);
        $media->addColumn('product_id', $idType, $fkOptions);
        $media->addColumn('path', 'string', ['length' => 255]);
        $media->addColumn('kind', 'string', ['length' => 55]);
        $media->addColumn('position', 'integer', ['default' => 0]);
        $media->addColumn('created_at', 'datetime');
        $media->addColumn('updated_at', 'datetime');
        $media->setPrimaryKey(['id']);
        $media->addIndex(['product_id'], 'IDX_CB70DA504584665A');

        $outbox = $schema->createTable('outbox_event');
        $outbox->addColumn('id', $idType, $idOptions);
        $outbox->addColumn('event_type', 'string', ['length' => 100]);
        $outbox->addColumn('event_version', 'smallint');
        $outbox->addColumn('created_at', 'datetime');
        $outbox->addColumn('payload', 'json');
        $outbox->addColumn('published_at', 'datetime', ['notnull' => false]);
        $outbox->setPrimaryKey(['id']);
        $outbox->addIndex(['published_at'], 'idx_outbox_pending');

        $product->addForeignKeyConstraint('brand', ['brand_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_D34A04AD44F5D353');
        $product->addForeignKeyConstraint('unit', ['unit_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_D34A04ADF8BD700D');
        $product->addForeignKeyConstraint('category', ['category_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_D34A04AD12469DE2');
        $variant->addForeignKeyConstraint('product', ['product_id'], ['id'], [], 'FK_209AA4124584665A');
        $media->addForeignKeyConstraint('product', ['product_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_2A83FB354584665A');

        return $schema;
    }
}
