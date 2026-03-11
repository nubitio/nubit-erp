<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the user_notification table in each tenant DB.
 *
 * This table drives the in-app notification bell in the React UI.
 * Each row belongs to a single user (user_id → usuario.usuario_id)
 * within the tenant's own database (one DB per tenant).
 *
 * read_at NULL  = unread
 * read_at !NULL = read (timestamp of when it was marked)
 *
 * The compound index (user_id, read_at, created_at) covers the two most
 * common queries:
 *   1. "Fetch unread notifications for user X" → WHERE user_id=? AND read_at IS NULL
 *   2. "Fetch recent notifications for user X" → WHERE user_id=? ORDER BY created_at DESC
 */
final class Version20260311000000_create_user_notification extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_notification table for in-app notification bell';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_notification (
    id         BIGINT       NOT NULL AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    type       VARCHAR(50)  NOT NULL COMMENT 'sunat_error|sunat_blocked|stock_low|cash_not_opened|quota_warning|daily_briefing',
    severity   VARCHAR(10)  NOT NULL COMMENT 'critical|warning|info',
    title      VARCHAR(200) NOT NULL,
    body       TEXT         NOT NULL,
    action_url VARCHAR(500) NULL,
    read_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_user_notification_user (user_id, read_at, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_notification');
    }
}
