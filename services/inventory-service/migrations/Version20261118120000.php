<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrective migration: inventory-service entity PKs and cross-service product
 * references moved from auto-increment integers to the Doctrine `uuid` type.
 *
 * On MySQL/SQLite the Symfony `uuid` type is persisted as a fixed 16-byte binary
 * (BINARY(16) on MySQL 8.4), so all id / foreign-id columns use that declaration.
 * Legacy development rows carry obsolete integer ids and cannot be converted;
 * they are intentionally dropped and the fixtures are reloaded afterwards.
 */
final class Version20261118120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate inventory, movement and product ids (including cross-service product ids) to UUID.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS inventory_movement');
        $this->addSql('DROP TABLE IF EXISTS inventory');
        $this->addSql('DROP TABLE IF EXISTS product');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');

        $this->addSql('CREATE TABLE product (id BINARY(16) NOT NULL, sku VARCHAR(64) DEFAULT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory (id BINARY(16) NOT NULL, product_id BINARY(16) NOT NULL, quantity_on_hand NUMERIC(12, 3) NOT NULL, quantity_reserved NUMERIC(12, 3) NOT NULL, reorder_level NUMERIC(12, 3) NOT NULL, version INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, PRIMARY KEY (id), UNIQUE INDEX UNIQ_B12D4A364584665A (product_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_movement (id BINARY(16) NOT NULL, inventory_id BINARY(16) NOT NULL, product_id BINARY(16) NOT NULL, type VARCHAR(32) NOT NULL, quantity NUMERIC(12, 3) NOT NULL, quantity_after NUMERIC(12, 3) DEFAULT NULL, quantity_delta NUMERIC(12, 3) DEFAULT NULL, balance_after NUMERIC(12, 3) DEFAULT NULL, reserved_delta NUMERIC(12, 3) DEFAULT NULL, reserved_after NUMERIC(12, 3) DEFAULT NULL, reference_type VARCHAR(32) DEFAULT NULL, reference_id VARCHAR(255) DEFAULT NULL, reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_40972F669EEA759 (inventory_id), PRIMARY KEY (id), CONSTRAINT FK_40972F669EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // Best effort: restore the previous integer-id schema. Old rows are not recoverable.
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        $this->addSql('DROP TABLE IF EXISTS inventory_movement');
        $this->addSql('DROP TABLE IF EXISTS inventory');
        $this->addSql('DROP TABLE IF EXISTS product');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1');

        $this->addSql('CREATE TABLE product (id INT UNSIGNED NOT NULL, sku VARCHAR(64) DEFAULT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory (id INT AUTO_INCREMENT NOT NULL, product_id INT NOT NULL, quantity_on_hand NUMERIC(12, 3) NOT NULL, quantity_reserved NUMERIC(12, 3) NOT NULL, reorder_level NUMERIC(12, 3) NOT NULL, version INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, PRIMARY KEY (id), UNIQUE INDEX UNIQ_B12D4A364584665A (product_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inventory_movement (id INT AUTO_INCREMENT NOT NULL, inventory_id INT NOT NULL, product_id INT NOT NULL, type VARCHAR(32) NOT NULL, quantity NUMERIC(12, 3) NOT NULL, quantity_after NUMERIC(12, 3) DEFAULT NULL, quantity_delta NUMERIC(12, 3) DEFAULT NULL, balance_after NUMERIC(12, 3) DEFAULT NULL, reserved_delta NUMERIC(12, 3) DEFAULT NULL, reserved_after NUMERIC(12, 3) DEFAULT NULL, reference_type VARCHAR(32) DEFAULT NULL, reference_id VARCHAR(255) DEFAULT NULL, reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_40972F669EEA759 (inventory_id), PRIMARY KEY (id), CONSTRAINT FK_40972F669EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}
