<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818120000_fixed_assets extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fixed assets: straight-line depreciation over a useful life, with a monthly '
            . 'depreciation entry audit trail — purely additive, no existing table changed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE fixed_asset (
                id CHAR(36) NOT NULL,
                description VARCHAR(255) NOT NULL,
                purchase_date DATE NOT NULL,
                cost NUMERIC(12, 2) NOT NULL,
                useful_life_months INT NOT NULL,
                source_bill_id CHAR(36) DEFAULT NULL,
                accumulated_depreciation NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                status VARCHAR(20) DEFAULT 'ACTIVE' NOT NULL,
                disposed_at DATETIME DEFAULT NULL,
                disposal_note LONGTEXT DEFAULT NULL,
                note LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_fixed_asset_status (status),
                INDEX IDX_FIXED_ASSET_SOURCE_BILL (source_bill_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE asset_depreciation_entry (
                id CHAR(36) NOT NULL,
                fixed_asset_id CHAR(36) NOT NULL,
                period DATE NOT NULL,
                amount NUMERIC(12, 2) NOT NULL,
                accumulated_after NUMERIC(12, 2) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_asset_depreciation_entry_fixed_asset (fixed_asset_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE fixed_asset ADD CONSTRAINT FK_FIXED_ASSET_SOURCE_BILL FOREIGN KEY (source_bill_id) REFERENCES supplier_bill (id)');
        $this->addSql('ALTER TABLE asset_depreciation_entry ADD CONSTRAINT FK_ASSET_DEPRECIATION_ENTRY_FIXED_ASSET FOREIGN KEY (fixed_asset_id) REFERENCES fixed_asset (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE asset_depreciation_entry');
        $this->addSql('DROP TABLE fixed_asset');
    }
}
