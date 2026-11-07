<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-tenant, append-only record of who did what in the account: sign-ins, invitations, second
 * factor, role permission changes, user creation and removal. Nothing in the application updates
 * or deletes these rows. The actor's name is stored next to the id so the entry still reads
 * correctly after the user is removed.
 *
 * Also grants the new activity.view permission to the administrator role only (least privilege).
 * The super administrator holds every permission implicitly.
 */
final class Version20261108000000_activity_event extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create identity_activity_event and grant activity.view to administrators';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE identity_activity_event (
                id BIGINT AUTO_INCREMENT NOT NULL,
                occurred_at DATETIME NOT NULL,
                actor_user_id INT DEFAULT NULL,
                actor_username VARCHAR(180) DEFAULT NULL,
                event_type VARCHAR(60) NOT NULL,
                is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
                summary VARCHAR(255) NOT NULL,
                detail VARCHAR(255) DEFAULT NULL,
                subject VARCHAR(180) DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                INDEX idx_identity_activity_event_occurred (occurred_at),
                INDEX idx_identity_activity_event_type (event_type, occurred_at),
                INDEX idx_identity_activity_event_actor (actor_username, occurred_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(
            "INSERT IGNORE INTO role_permission (role_id, permission) SELECT 2, 'activity.view' WHERE EXISTS (SELECT 1 FROM rol WHERE rol_id = 2)",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission = 'activity.view'");
        $this->addSql('DROP TABLE identity_activity_event');
    }
}
