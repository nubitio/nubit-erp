<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NB-10 follow-up: pharmacy_prescriptions (a prescription on file for a
 * dispensing) and pharmacy_products.controlled_substance_code (DIGEMID code for
 * controlled products).
 */
final class Version20260907090000_pharmacy_prescriptions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pharmacy_prescriptions and add pharmacy_products.controlled_substance_code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE pharmacy_prescriptions (
                id INT AUTO_INCREMENT NOT NULL,
                sale_id VARCHAR(255) DEFAULT NULL,
                patient_name VARCHAR(255) NOT NULL,
                patient_document VARCHAR(32) DEFAULT NULL,
                prescriber_name VARCHAR(255) NOT NULL,
                prescriber_registration VARCHAR(64) DEFAULT NULL,
                issued_on DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
                product_ids JSON NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_pharmacy_prescription_sale (sale_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE pharmacy_products ADD controlled_substance_code VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pharmacy_products DROP controlled_substance_code');
        $this->addSql('DROP TABLE pharmacy_prescriptions');
    }
}
