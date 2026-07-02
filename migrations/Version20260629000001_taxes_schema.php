<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629000001_taxes_schema extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add detraction deposit tracking, detraction account, and retention/perception table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_document ADD detraction_deposited_at DATETIME DEFAULT NULL AFTER detraction_value_ref');

        $this->addSql('ALTER TABLE tenant_settings ADD detraction_account VARCHAR(30) DEFAULT NULL AFTER rc');

        $this->addSql('
            CREATE TABLE retention_percepcion (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                type VARCHAR(20) NOT NULL COMMENT \'retencion|percepcion\',
                direction VARCHAR(20) NOT NULL COMMENT \'sufrida|efectuada\',
                counterparty_name VARCHAR(255) NOT NULL,
                document_reference VARCHAR(50) NOT NULL,
                rate DECIMAL(8,4) NOT NULL DEFAULT \'0.0000\',
                base_amount DECIMAL(12,2) NOT NULL DEFAULT \'0.00\',
                amount DECIMAL(12,2) NOT NULL DEFAULT \'0.00\',
                period_date DATE NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS retention_percepcion');
        $this->addSql('ALTER TABLE tenant_settings DROP COLUMN IF EXISTS detraction_account');
        $this->addSql('ALTER TABLE tax_document DROP COLUMN IF EXISTS detraction_deposited_at');
    }
}
