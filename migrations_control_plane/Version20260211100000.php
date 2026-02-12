<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_backups table and add backup_frequency to tenants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE tenant_backups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                filename VARCHAR(500) NOT NULL,
                storage_path VARCHAR(1000) NOT NULL,
                size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                storage_type ENUM(\'local\', \'s3\') NOT NULL DEFAULT \'local\',
                status ENUM(\'pending\', \'completed\', \'failed\') NOT NULL DEFAULT \'pending\',
                error_message TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                INDEX IDX_BACKUP_TENANT (tenant_id),
                INDEX IDX_BACKUP_STATUS (status),
                INDEX IDX_BACKUP_CREATED (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');

        $this->addSql("
            ALTER TABLE tenants ADD COLUMN backup_frequency INT NOT NULL DEFAULT 0
                COMMENT '0=disabled, 24=daily, 168=weekly, 720=monthly (hours)'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tenant_backups');
        $this->addSql('ALTER TABLE tenants DROP COLUMN backup_frequency');
    }
}
