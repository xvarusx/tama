<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113212401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create outbox_event table for domain event tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE outbox_event (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, published_at DATETIME DEFAULT NULL, event_type VARCHAR(100) NOT NULL, event_version SMALLINT NOT NULL, payload JSON NOT NULL, INDEX idx_outbox_pending (published_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE outbox_event');
    }
}
