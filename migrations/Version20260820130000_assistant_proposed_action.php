<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820130000_assistant_proposed_action extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store AI propose-and-confirm actions. Writes happen only after the user confirms.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE assistant_proposed_action (
                id CHAR(36) NOT NULL,
                user_id INT NOT NULL,
                kind VARCHAR(64) NOT NULL,
                payload JSON NOT NULL,
                summary LONGTEXT NOT NULL,
                status VARCHAR(20) NOT NULL,
                idempotency_key VARCHAR(64) NOT NULL,
                result_ref VARCHAR(64) DEFAULT NULL,
                error_message LONGTEXT DEFAULT NULL,
                expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_assistant_action_idempotency (idempotency_key),
                INDEX idx_assistant_action_user_status (user_id, status),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE assistant_proposed_action');
    }
}
