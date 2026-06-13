<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Captura el comprobante tributario del proveedor en la recepción de mercadería
 * (goods_receipts) para poder generar el Registro de Compras electrónico (PLE 8.1).
 */
final class Version20260612160000_purchase_supplier_document extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add supplier_doc_type/series/number to goods_receipts for PLE 8.1 (Registro de Compras).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE goods_receipts
                ADD supplier_doc_type VARCHAR(2) DEFAULT NULL,
                ADD supplier_doc_series VARCHAR(20) DEFAULT NULL,
                ADD supplier_doc_number VARCHAR(20) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE goods_receipts
                DROP supplier_doc_type,
                DROP supplier_doc_series,
                DROP supplier_doc_number
        SQL);
    }
}
