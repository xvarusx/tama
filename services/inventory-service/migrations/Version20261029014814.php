<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

/**
 * Replaces the legacy SKU-based inventory table with product quantity columns
 * and adds the movement ledger.
 */
final class Version20261029014814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move inventory from legacy SKU columns to product quantities and add the movement ledger.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventory_movement (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, type VARCHAR(32) NOT NULL, quantity NUMERIC(12, 3) NOT NULL, reference_type VARCHAR(32) DEFAULT NULL, reference_id VARCHAR(255) DEFAULT NULL, reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, inventory_id INT NOT NULL, INDEX IDX_40972F669EEA759 (inventory_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_40972F669EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id)');
        $this->addSql('ALTER TABLE inventory ADD product_id INT NOT NULL, ADD quantity_on_hand NUMERIC(12, 3) NOT NULL, ADD quantity_reserved NUMERIC(12, 3) NOT NULL, ADD reorder_level NUMERIC(12, 3) NOT NULL, ADD version INT DEFAULT 1 NOT NULL, DROP sku, DROP name, DROP description, DROP quantity, DROP reserved_quantity, DROP min_stock_level, DROP location, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B12D4A364584665A ON inventory (product_id)');
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('The legacy SKU columns cannot be restored: product ids replaced SKUs and the dropped columns have no source data.');
    }
}
