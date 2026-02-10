<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create control plane tables for tenants and tenant domains';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenants (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, connection VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT \'active\', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_TENANT_NAME (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tenant_domains (id INT AUTO_INCREMENT NOT NULL, tenant_id INT NOT NULL, domain VARCHAR(255) NOT NULL, is_primary TINYINT(1) NOT NULL DEFAULT 0, UNIQUE INDEX UNIQ_TENANT_DOMAIN (domain), INDEX IDX_TENANT_DOMAIN_TENANT (tenant_id), PRIMARY KEY(id), CONSTRAINT FK_TENANT_DOMAIN_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenant_domains');
        $this->addSql('DROP TABLE tenants');
    }
}
