<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210132000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit log table for control plane';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_logs (id INT AUTO_INCREMENT NOT NULL, tenant_id INT DEFAULT NULL, action VARCHAR(255) NOT NULL, actor VARCHAR(255) DEFAULT NULL, request_id VARCHAR(64) DEFAULT NULL, metadata JSON NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_AUDIT_LOG_TENANT (tenant_id), INDEX IDX_AUDIT_LOG_ACTION (action), INDEX IDX_AUDIT_LOG_REQUEST (request_id), PRIMARY KEY(id), CONSTRAINT FK_AUDIT_LOG_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
    }
}
