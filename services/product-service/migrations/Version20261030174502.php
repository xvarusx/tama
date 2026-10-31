<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261030174502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill product status to active for rows created before the status column existed.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE product SET status = 'active' WHERE status NOT IN ('active', 'archive')");
    }

    public function down(Schema $schema): void
    {
        // The pre-existing status values are unknown, so there is nothing to restore.
    }
}
