<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031161000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the local product projection used to synchronize inventory state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product (id INT UNSIGNED NOT NULL, sku VARCHAR(64) DEFAULT NULL, name VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product');
    }
}
