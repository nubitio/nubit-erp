<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204000000_tax_documents_unification extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create unified tax document ledger with immutable line snapshots and SUNAT events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE tax_document (
    tax_document_id CHAR(36) NOT NULL,
    movimiento_id VARCHAR(255) NOT NULL,
    related_tax_document_id CHAR(36) DEFAULT NULL,
    branch_id VARCHAR(255) DEFAULT NULL,
    customer_id VARCHAR(11) DEFAULT NULL,
    document_type VARCHAR(10) NOT NULL,
    issue_date DATETIME NOT NULL,
    series VARCHAR(10) NOT NULL,
    number VARCHAR(20) NOT NULL,
    affected_document VARCHAR(50) DEFAULT NULL,
    reason_code VARCHAR(10) DEFAULT NULL,
    reason_description VARCHAR(255) DEFAULT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
    payment_method INT DEFAULT NULL,
    subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0,
    taxable_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    igv_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    rc_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0,
    discount_percent DECIMAL(10, 2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    operation_type VARCHAR(4) DEFAULT NULL,
    detraction_code VARCHAR(10) DEFAULT NULL,
    detraction_percent DECIMAL(10, 2) DEFAULT NULL,
    detraction_amount DECIMAL(10, 2) DEFAULT NULL,
    detraction_payment_method VARCHAR(10) DEFAULT NULL,
    detraction_value_ref DECIMAL(10, 2) DEFAULT NULL,
    sunat_status VARCHAR(50) DEFAULT NULL,
    sunat_message LONGTEXT DEFAULT NULL,
    digest VARCHAR(255) DEFAULT NULL,
    void_ticket VARCHAR(255) DEFAULT NULL,
    void_correlation VARCHAR(255) DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    accepted_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY(tax_document_id),
    UNIQUE INDEX UNIQ_TAX_DOCUMENT_NUMBER (document_type, series, number),
    INDEX IDX_TAX_DOCUMENT_MOVIMIENTO (movimiento_id),
    INDEX IDX_TAX_DOCUMENT_RELATED (related_tax_document_id),
    INDEX IDX_TAX_DOCUMENT_BRANCH (branch_id),
    INDEX IDX_TAX_DOCUMENT_CUSTOMER (customer_id),
    CONSTRAINT FK_TAX_DOCUMENT_MOVIMIENTO FOREIGN KEY (movimiento_id) REFERENCES movimiento (movimiento_id) ON DELETE CASCADE,
    CONSTRAINT FK_TAX_DOCUMENT_RELATED FOREIGN KEY (related_tax_document_id) REFERENCES tax_document (tax_document_id) ON DELETE SET NULL,
    CONSTRAINT FK_TAX_DOCUMENT_BRANCH FOREIGN KEY (branch_id) REFERENCES `local` (local_id),
    CONSTRAINT FK_TAX_DOCUMENT_CUSTOMER FOREIGN KEY (customer_id) REFERENCES cliente (ruc)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE tax_document_line (
    id INT AUTO_INCREMENT NOT NULL,
    tax_document_id CHAR(36) NOT NULL,
    line_number INT NOT NULL,
    product_id VARCHAR(255) DEFAULT NULL,
    product_code VARCHAR(255) DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_uom VARCHAR(20) DEFAULT NULL,
    quantity DECIMAL(10, 6) NOT NULL DEFAULT 0,
    unit_price DECIMAL(12, 6) NOT NULL DEFAULT 0,
    unit_value DECIMAL(12, 6) DEFAULT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT NULL,
    taxed_amount DECIMAL(10, 2) DEFAULT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT NULL,
    tax_percentage DECIMAL(10, 2) DEFAULT NULL,
    tax_type VARCHAR(4) DEFAULT NULL,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE INDEX UNIQ_TAX_DOCUMENT_LINE (tax_document_id, line_number),
    INDEX IDX_TAX_DOCUMENT_LINE_PRODUCT (product_id),
    CONSTRAINT FK_TAX_DOCUMENT_LINE_DOCUMENT FOREIGN KEY (tax_document_id) REFERENCES tax_document (tax_document_id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE tax_document_event (
    id INT AUTO_INCREMENT NOT NULL,
    tax_document_id CHAR(36) NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    status VARCHAR(30) DEFAULT NULL,
    code VARCHAR(50) DEFAULT NULL,
    message LONGTEXT DEFAULT NULL,
    payload_json LONGTEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    INDEX IDX_TAX_DOCUMENT_EVENT_DOCUMENT (tax_document_id),
    CONSTRAINT FK_TAX_DOCUMENT_EVENT_DOCUMENT FOREIGN KEY (tax_document_id) REFERENCES tax_document (tax_document_id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO tax_document (
    tax_document_id,
    movimiento_id,
    related_tax_document_id,
    branch_id,
    customer_id,
    document_type,
    issue_date,
    series,
    number,
    affected_document,
    reason_code,
    reason_description,
    currency,
    payment_method,
    subtotal,
    taxable_amount,
    igv_amount,
    rc_amount,
    total,
    discount_percent,
    discount_amount,
    operation_type,
    detraction_code,
    detraction_percent,
    detraction_amount,
    detraction_payment_method,
    detraction_value_ref,
    sunat_status,
    sunat_message,
    digest,
    void_ticket,
    void_correlation,
    sent_at,
    accepted_at,
    created_at,
    updated_at
)
SELECT
    UUID(),
    c.movimiento_id,
    NULL,
    c.branch_id,
    c.cliente_id,
    c.tipo_comprobante_id,
    c.fecha,
    c.serie,
    c.numero,
    NULL,
    NULL,
    NULL,
    'PEN',
    m.forma_pago,
    c.subtotal,
    c.gravada,
    c.igv,
    c.rc,
    c.total,
    c.discount_percent,
    c.discount_amount,
    c.tipo_operacion,
    c.detraccion_codigo,
    c.detraccion_porcentaje,
    c.detraccion_monto,
    c.detraccion_medio_pago,
    c.detraccion_valor_referencial,
    c.envio_sunat,
    c.mensaje_sunat,
    c.digest,
    c.ticket_baja,
    c.correlativo_baja,
    NULL,
    CASE WHEN c.envio_sunat = '0' THEN c.updated_at ELSE NULL END,
    c.created_at,
    c.updated_at
FROM comprobante c
INNER JOIN movimiento m ON m.movimiento_id = c.movimiento_id
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO tax_document (
    tax_document_id,
    movimiento_id,
    related_tax_document_id,
    branch_id,
    customer_id,
    document_type,
    issue_date,
    series,
    number,
    affected_document,
    reason_code,
    reason_description,
    currency,
    payment_method,
    subtotal,
    taxable_amount,
    igv_amount,
    rc_amount,
    total,
    discount_percent,
    discount_amount,
    operation_type,
    detraction_code,
    detraction_percent,
    detraction_amount,
    detraction_payment_method,
    detraction_value_ref,
    sunat_status,
    sunat_message,
    digest,
    void_ticket,
    void_correlation,
    sent_at,
    accepted_at,
    created_at,
    updated_at
)
SELECT
    UUID(),
    n.movimiento_id,
    td.tax_document_id,
    n.branch_id,
    m.cliente_id,
    n.tipo_id,
    n.fecha,
    n.serie,
    CAST(n.correlativo AS CHAR),
    n.doc_afectado,
    n.codigo_motivo,
    n.descripcion_motivo,
    'PEN',
    m.forma_pago,
    n.total,
    n.total,
    0,
    0,
    n.total,
    0,
    0,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    n.envio_sunat,
    n.mensaje_sunat,
    n.digest,
    NULL,
    NULL,
    NULL,
    CASE WHEN n.envio_sunat = '0' THEN n.updated_at ELSE NULL END,
    n.created_at,
    n.updated_at
FROM nota n
INNER JOIN movimiento m ON m.movimiento_id = n.movimiento_id
LEFT JOIN tax_document td
    ON td.movimiento_id = n.movimiento_id
   AND td.document_type IN ('01', '03')
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO tax_document_line (
    tax_document_id,
    line_number,
    product_id,
    product_code,
    product_name,
    product_uom,
    quantity,
    unit_price,
    unit_value,
    discount_amount,
    taxed_amount,
    tax_amount,
    tax_percentage,
    tax_type,
    total,
    created_at
)
SELECT
    td.tax_document_id,
    md.id,
    p.producto_id,
    p.codigo,
    p.descripcion,
    u.code,
    md.cantidad,
    md.precio_unitario,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    '10',
    md.precio_total,
    td.created_at
FROM tax_document td
INNER JOIN movimiento_detalle md ON md.movimiento_id = td.movimiento_id
LEFT JOIN producto p ON p.producto_id = md.producto_id
LEFT JOIN unidad u ON u.unidad_id = p.unidad_id
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO tax_document_event (
    tax_document_id,
    event_type,
    status,
    code,
    message,
    payload_json,
    created_at
)
SELECT
    td.tax_document_id,
    'legacy_import',
    CASE
        WHEN td.sunat_status IS NULL OR td.sunat_status = '' OR td.sunat_status = 'pendiente' THEN 'pending'
        WHEN td.sunat_status = '0' THEN 'accepted'
        ELSE 'error'
    END,
    td.sunat_status,
    td.sunat_message,
    NULL,
    td.created_at
FROM tax_document td
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tax_document_event');
        $this->addSql('DROP TABLE tax_document_line');
        $this->addSql('DROP TABLE tax_document');
    }
}
