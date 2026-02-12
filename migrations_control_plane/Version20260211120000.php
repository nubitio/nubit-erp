<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant_provisions table for tracking provisioning status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE tenant_provisions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                tenant_name VARCHAR(255) NOT NULL,
                domain VARCHAR(255) NOT NULL DEFAULT '',
                admin_user VARCHAR(128) NOT NULL DEFAULT 'admin',
                target_container VARCHAR(255) NOT NULL DEFAULT '',
                status ENUM('dispatched','running','completed','failed') NOT NULL DEFAULT 'dispatched',
                error_message TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                INDEX IDX_PROV_TENANT (tenant_id),
                INDEX IDX_PROV_STATUS (status),
                INDEX IDX_PROV_CREATED (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tenant_provisions');
    }
}
