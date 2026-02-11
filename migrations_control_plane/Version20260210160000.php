<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tenant_usage table for tracking tenant metrics over time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenant_usage (
            id INT AUTO_INCREMENT NOT NULL,
            tenant_id INT NOT NULL,
            collected_at DATETIME NOT NULL,
            metric_name VARCHAR(64) NOT NULL,
            metric_value BIGINT NOT NULL DEFAULT 0,
            INDEX IDX_TENANT_USAGE_TENANT (tenant_id),
            INDEX IDX_TENANT_USAGE_COLLECTED (collected_at),
            INDEX IDX_TENANT_USAGE_METRIC (metric_name),
            INDEX IDX_TENANT_USAGE_TENANT_METRIC_TIME (tenant_id, metric_name, collected_at),
            UNIQUE INDEX UNIQ_TENANT_USAGE_SAMPLE (tenant_id, collected_at, metric_name),
            PRIMARY KEY(id),
            CONSTRAINT FK_TENANT_USAGE_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenant_usage');
    }
}
