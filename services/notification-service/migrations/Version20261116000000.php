<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification service tables: notifications, deliveries, delivery_attempts, notification_preferences, notification_templates, processed_events, settings';
    }

    public function up(Schema $schema): void
    {
        // notifications table
        $table = $schema->createTable('notifications');
        $table->addColumn('id', 'uuid');
        $table->setPrimaryKey(['id']);
        $table->addColumn('recipient_id', 'integer');
        $table->addColumn('type', 'string', ['length' => 120]);
        $table->addColumn('title', 'string', ['length' => 255]);
        $table->addColumn('body', 'text');
        $table->addColumn('severity', 'string', ['length' => 20, 'default' => 'info']);
        $table->addColumn('status', 'string', ['length' => 20, 'default' => 'unread']);
        $table->addColumn('visible_in_app', 'boolean', ['default' => true]);
        $table->addColumn('dedup_key', 'string', ['length' => 190, 'notnull' => false]);
        $table->addColumn('link', 'string', ['length' => 500, 'notnull' => false]);
        $table->addColumn('read_at', 'datetime', ['notnull' => false]);
        $table->addColumn('archived_at', 'datetime', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);

        $table->addIndex(
            ['recipient_id', 'visible_in_app', 'status', 'created_at'],
            'idx_notifications_inbox'
        );
        $table->addUniqueIndex(
            ['recipient_id', 'dedup_key'],
            'uniq_notifications_recipient_dedup'
        );

        // deliveries table
        $table = $schema->createTable('deliveries');
        $table->addColumn('id', 'uuid');
        $table->setPrimaryKey(['id']);
        $table->addColumn('notification_id', 'uuid');
        $table->addColumn('channel', 'string', ['length' => 20]);
        $table->addColumn('status', 'string', ['length' => 20, 'default' => 'queued']);
        $table->addColumn('attempts', 'integer', ['default' => 0]);
        $table->addColumn('recipient_email', 'string', ['length' => 180]);
        $table->addColumn('subject', 'string', ['length' => 255]);
        $table->addColumn('body', 'text');
        $table->addColumn('next_attempt_at', 'datetime', ['notnull' => false]);
        $table->addColumn('sending_at', 'datetime', ['notnull' => false]);
        $table->addColumn('last_error', 'text', ['notnull' => false]);
        $table->addColumn('sent_at', 'datetime', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime', ['notnull' => false]);

        $table->addForeignKeyConstraint(
            'notifications',
            ['notification_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );

        $table->addIndex(['status', 'next_attempt_at'], 'idx_deliveries_due');
        $table->addIndex(['status', 'sending_at'], 'idx_deliveries_stale');
        $table->addIndex(['recipient_email'], 'idx_deliveries_recipient_email');

        // delivery_attempts table
        $table = $schema->createTable('delivery_attempts');
        $table->addColumn('id', 'uuid');
        $table->setPrimaryKey(['id']);
        $table->addColumn('delivery_id', 'uuid');
        $table->addColumn('attempt_number', 'integer');
        $table->addColumn('provider_message_id', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('error', 'text', ['notnull' => false]);
        $table->addColumn('attempted_at', 'datetime');

        $table->addForeignKeyConstraint(
            'deliveries',
            ['delivery_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );

        $table->addIndex(['delivery_id'], 'idx_delivery_attempts_delivery');

        // notification_preferences table
        $table = $schema->createTable('notification_preferences');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        $table->addColumn('user_id', 'integer');
        $table->addColumn('type', 'string', ['length' => 120]);
        $table->addColumn('channel', 'string', ['length' => 20]);
        $table->addColumn('enabled', 'boolean');

        $table->addUniqueIndex(
            ['user_id', 'type', 'channel'],
            'uniq_notification_preferences'
        );

        // notification_templates table
        $table = $schema->createTable('notification_templates');
        $table->addColumn('id', 'uuid');
        $table->setPrimaryKey(['id']);
        $table->addColumn('type', 'string', ['length' => 120]);
        $table->addColumn('channel', 'string', ['length' => 20]);
        $table->addColumn('locale', 'string', ['length' => 5]);
        $table->addColumn('subject', 'string', ['length' => 255]);
        $table->addColumn('body_template', 'text');

        $table->addUniqueIndex(
            ['type', 'channel', 'locale'],
            'uniq_notification_templates'
        );

        // processed_events table
        $table = $schema->createTable('processed_events');
        $table->addColumn('event_id', 'uuid');
        $table->setPrimaryKey(['event_id']);
        $table->addColumn('source', 'string', ['length' => 100]);
        $table->addColumn('processed_at', 'datetime', ['notnull' => false]);

        // settings table
        $table = $schema->createTable('settings');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey(['id']);
        // "key" is a reserved word on MySQL; the entity maps this column as setting_key.
        $table->addColumn('setting_key', 'string', ['length' => 190]);
        $table->addColumn('value', 'text');
        $table->addColumn('description', 'string', ['length' => 255]);

        $table->addUniqueIndex(['setting_key'], 'uniq_settings_key');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('notifications');
        $schema->dropTable('deliveries');
        $schema->dropTable('delivery_attempts');
        $schema->dropTable('notification_preferences');
        $schema->dropTable('notification_templates');
        $schema->dropTable('processed_events');
        $schema->dropTable('settings');
    }
}