<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Guía de Remisión Electrónica (GRE) — Remitente. Tablas despatch_guide y
 * despatch_guide_line para modelar el traslado de bienes enviado a SUNAT.
 */
final class Version20260612170000_despatch_guide extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create despatch_guide and despatch_guide_line tables for electronic shipping guides (GRE).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE despatch_guide (
                id VARCHAR(36) NOT NULL,
                branch_id VARCHAR(36) DEFAULT NULL,
                sale_id VARCHAR(36) DEFAULT NULL,
                document_type VARCHAR(2) NOT NULL DEFAULT '09',
                series VARCHAR(10) NOT NULL,
                number VARCHAR(20) DEFAULT NULL,
                issue_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
                transfer_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
                transfer_reason VARCHAR(2) NOT NULL,
                transfer_reason_description VARCHAR(255) DEFAULT NULL,
                transport_mode VARCHAR(2) NOT NULL,
                total_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
                weight_unit VARCHAR(5) NOT NULL DEFAULT 'KGM',
                package_count INT DEFAULT NULL,
                recipient_doc_type VARCHAR(2) NOT NULL DEFAULT '6',
                recipient_doc_number VARCHAR(15) NOT NULL,
                recipient_name VARCHAR(255) NOT NULL,
                origin_ubigeo VARCHAR(6) NOT NULL,
                origin_address VARCHAR(255) NOT NULL,
                destination_ubigeo VARCHAR(6) NOT NULL,
                destination_address VARCHAR(255) NOT NULL,
                carrier_doc_type VARCHAR(2) DEFAULT NULL,
                carrier_doc_number VARCHAR(15) DEFAULT NULL,
                carrier_name VARCHAR(255) DEFAULT NULL,
                carrier_mtc VARCHAR(30) DEFAULT NULL,
                vehicle_plate VARCHAR(10) DEFAULT NULL,
                driver_doc_type VARCHAR(2) DEFAULT NULL,
                driver_doc_number VARCHAR(15) DEFAULT NULL,
                driver_names VARCHAR(255) DEFAULT NULL,
                driver_last_names VARCHAR(255) DEFAULT NULL,
                driver_license VARCHAR(20) DEFAULT NULL,
                sunat_status VARCHAR(50) DEFAULT NULL,
                sunat_message LONGTEXT DEFAULT NULL,
                sunat_ticket VARCHAR(100) DEFAULT NULL,
                cdr_path VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                INDEX IDX_DESPATCH_SERIES_NUMBER (series, number),
                INDEX IDX_DESPATCH_ISSUE_DATE (issue_date),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE despatch_guide_line (
                id VARCHAR(36) NOT NULL,
                despatch_guide_id VARCHAR(36) NOT NULL,
                product_code VARCHAR(50) DEFAULT NULL,
                sunat_product_code VARCHAR(10) DEFAULT NULL,
                description VARCHAR(500) NOT NULL,
                unit VARCHAR(10) NOT NULL DEFAULT 'NIU',
                quantity NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
                INDEX IDX_DESPATCH_LINE_GUIDE (despatch_guide_id),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE despatch_guide_line
                ADD CONSTRAINT FK_DESPATCH_LINE_GUIDE
                FOREIGN KEY (despatch_guide_id) REFERENCES despatch_guide (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE despatch_guide_line DROP FOREIGN KEY FK_DESPATCH_LINE_GUIDE');
        $this->addSql('DROP TABLE despatch_guide_line');
        $this->addSql('DROP TABLE despatch_guide');
    }
}
