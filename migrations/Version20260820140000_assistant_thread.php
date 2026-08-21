<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820140000_assistant_thread extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist AI assistant conversations per user so a cashier can resume after refresh.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE assistant_thread (
                id CHAR(36) NOT NULL,
                user_id INT NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_assistant_thread_user_status (user_id, status, updated_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE assistant_thread_message (
                id CHAR(36) NOT NULL,
                thread_id CHAR(36) NOT NULL,
                role VARCHAR(20) NOT NULL,
                content LONGTEXT NOT NULL,
                actions JSON DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_assistant_message_thread_created (thread_id, created_at),
                PRIMARY KEY(id),
                CONSTRAINT fk_assistant_message_thread FOREIGN KEY (thread_id)
                    REFERENCES assistant_thread (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE assistant_thread_message');
        $this->addSql('DROP TABLE assistant_thread');
    }
}
