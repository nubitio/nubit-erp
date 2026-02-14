<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create binlog_backups table for binary log backup tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE binlog_backups (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            binlog_file VARCHAR(255) NOT NULL,
            binlog_position BIGINT UNSIGNED NOT NULL,
            filename VARCHAR(500) NOT NULL,
            storage_path VARCHAR(1000) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
            error_message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME NULL,
            INDEX IDX_BINLOG_STATUS (status),
            INDEX IDX_BINLOG_CREATED (created_at),
            INDEX IDX_BINLOG_FILE (binlog_file)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS binlog_backups');
    }
}
