<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Exception\IrreversibleMigration;

/**
 * Adds the soft-delete marker to inventory and the signed delta / balance-after
 * columns that let automatic adjustment movements describe the stock change
 * that produced them.
 */
final class Version20261029160411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add inventory soft delete and signed movement deltas with balance-after values.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory ADD deleted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD quantity_delta NUMERIC(12, 3) DEFAULT NULL, ADD balance_after NUMERIC(12, 3) DEFAULT NULL, ADD reserved_delta NUMERIC(12, 3) DEFAULT NULL, ADD reserved_after NUMERIC(12, 3) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        throw new IrreversibleMigration('The automatic movement deltas and soft-delete markers encode audit state that cannot be reconstructed once the columns are dropped.');
    }
}
