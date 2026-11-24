<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace the remaining integer ids with UUIDs.
 *
 * Symfony's "uuid" DBAL type (used by every other UUID column in this service)
 * stores the 16 raw bytes of the UUID: BINARY(16) on MySQL 8.4. CHAR(36) would
 * be unreadable by UuidType::convertToPHPValue(), so the new columns use the
 * same storage as the existing notifications.id / deliveries.id columns.
 */
final class Version20261118000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate notification recipient/user references and preference/setting PKs to UUID';
    }

    public function up(Schema $schema): void
    {
        // Existing dev rows reference integer auth ids; fixtures recreate them.
        $this->addSql('DELETE FROM delivery_attempts');
        $this->addSql('DELETE FROM deliveries');
        $this->addSql('DELETE FROM notification_preferences');
        $this->addSql('DELETE FROM notifications');
        $this->addSql('DELETE FROM settings');

        $this->addSql(
            'ALTER TABLE notifications DROP INDEX idx_notifications_inbox, DROP INDEX uniq_notifications_recipient_dedup, '
            . 'MODIFY recipient_id BINARY(16) NOT NULL, '
            . 'ADD INDEX idx_notifications_inbox (recipient_id, visible_in_app, status, created_at), '
            . 'ADD UNIQUE INDEX uniq_notifications_recipient_dedup (recipient_id, dedup_key)'
        );

        $this->addSql(
            'ALTER TABLE notification_preferences DROP INDEX uniq_notification_preferences, DROP PRIMARY KEY, '
            . 'MODIFY id BINARY(16) NOT NULL, MODIFY user_id BINARY(16) NOT NULL, '
            . 'ADD PRIMARY KEY (id), '
            . 'ADD UNIQUE INDEX uniq_notification_preferences (user_id, type, channel)'
        );

        $this->addSql('ALTER TABLE settings DROP PRIMARY KEY, MODIFY id BINARY(16) NOT NULL, ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema): void
    {
        // Best effort only: UUID values cannot be mapped back to the old integer ids.
        $this->addSql('DELETE FROM delivery_attempts');
        $this->addSql('DELETE FROM deliveries');
        $this->addSql('DELETE FROM notification_preferences');
        $this->addSql('DELETE FROM notifications');
        $this->addSql('DELETE FROM settings');

        $this->addSql(
            'ALTER TABLE notifications DROP INDEX idx_notifications_inbox, DROP INDEX uniq_notifications_recipient_dedup, '
            . 'MODIFY recipient_id INT NOT NULL, '
            . 'ADD INDEX idx_notifications_inbox (recipient_id, visible_in_app, status, created_at), '
            . 'ADD UNIQUE INDEX uniq_notifications_recipient_dedup (recipient_id, dedup_key)'
        );

        $this->addSql(
            'ALTER TABLE notification_preferences DROP INDEX uniq_notification_preferences, DROP PRIMARY KEY, '
            . 'MODIFY id INT AUTO_INCREMENT NOT NULL, MODIFY user_id INT NOT NULL, '
            . 'ADD PRIMARY KEY (id), '
            . 'ADD UNIQUE INDEX uniq_notification_preferences (user_id, type, channel)'
        );

        $this->addSql('ALTER TABLE settings DROP PRIMARY KEY, MODIFY id INT AUTO_INCREMENT NOT NULL, ADD PRIMARY KEY (id)');
    }
}
