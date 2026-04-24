<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260424142000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenant certificate metadata table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tenant_certificates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            tenant_name VARCHAR(63) NOT NULL,
            ruc VARCHAR(11) NOT NULL,
            status ENUM('active', 'expiring_soon', 'expired', 'revoked', 'superseded') NOT NULL DEFAULT 'active',
            subject_cn VARCHAR(255) NULL,
            subject_json JSON NULL,
            issuer_cn VARCHAR(255) NULL,
            issuer_json JSON NULL,
            serial_number VARCHAR(128) NULL,
            fingerprint_sha256 CHAR(64) NOT NULL,
            valid_from DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            uploaded_by VARCHAR(190) NULL,
            revoked_at DATETIME NULL,
            revoked_by VARCHAR(190) NULL,
            revocation_reason TEXT NULL,
            superseded_by_id INT UNSIGNED NULL,
            last_alerted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX IDX_TENANT_CERT_TENANT (tenant_id),
            INDEX IDX_TENANT_CERT_RUC (ruc),
            INDEX IDX_TENANT_CERT_STATUS (status),
            INDEX IDX_TENANT_CERT_EXPIRES (expires_at),
            UNIQUE INDEX UNIQ_TENANT_CERT_FINGERPRINT (tenant_id, fingerprint_sha256),
            CONSTRAINT FK_TENANT_CERT_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
            CONSTRAINT FK_TENANT_CERT_SUPERSEDED_BY FOREIGN KEY (superseded_by_id) REFERENCES tenant_certificates (id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tenant_certificates');
    }
}
