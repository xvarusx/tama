<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correct the user.id primary key from INT AUTO_INCREMENT to a UUID (CHAR(36)).
 *
 * This is a destructive, table-recreate migration: it drops the existing `user`
 * table and rebuilds it with the same columns but a CHAR(36) UUID primary key.
 * The four development fixture users are intentionally not preserved; reload
 * them with `bin/console doctrine:fixtures:load --no-interaction` after migrating.
 */
final class Version20261118120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate user.id from INT AUTO_INCREMENT to UUID (CHAR(36)) by recreating the table (dev fixtures are reloaded).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, api_token VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, update_at DATETIME DEFAULT NULL, login_at DATETIME DEFAULT NULL, logout_at DATETIME DEFAULT NULL, is_verified TINYINT NOT NULL, full_name VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, api_token VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, update_at DATETIME DEFAULT NULL, login_at DATETIME DEFAULT NULL, logout_at DATETIME DEFAULT NULL, is_verified TINYINT NOT NULL, full_name VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }
}
