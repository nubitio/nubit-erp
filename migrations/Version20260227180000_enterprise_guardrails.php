<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227180000_enterprise_guardrails extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enterprise DB guardrails: branch-warehouse/register consistency, one open cash session per register, stronger voucher/document uniqueness.';
    }

    public function up(Schema $schema): void
    {
        // One series per branch and document type.
        $this->addSql('CREATE UNIQUE INDEX uq_local_comprobante_branch_type_series ON local_comprobante (local_id, tipo_comprobante_id, serie)');

        // Document uniqueness should be scoped by branch.
        $this->addSql('DROP INDEX UNIQ_TAX_DOCUMENT_NUMBER ON tax_document');
        $this->addSql('CREATE UNIQUE INDEX uq_tax_document_number_branch ON tax_document (document_type, branch_id, series, number)');

        // Prepare composite references needed for cross-table consistency FKs.
        $this->addSql('CREATE UNIQUE INDEX uq_almacen_id_local ON almacen (almacen_id, local_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_cash_register_id_branch ON cash_register (id, branch_id)');
        $this->addSql('CREATE UNIQUE INDEX uq_cash_sessions_id_branch ON cash_sessions (id, branch_id)');

        // Enforce sales.branch_id == warehouse.local_id and register/session branch alignment.
        $this->addSql('ALTER TABLE sales ADD CONSTRAINT fk_sales_warehouse_branch FOREIGN KEY (warehouse_id, branch_id) REFERENCES almacen (almacen_id, local_id)');
        $this->addSql('ALTER TABLE sales ADD CONSTRAINT fk_sales_register_branch FOREIGN KEY (cash_register_id, branch_id) REFERENCES cash_register (id, branch_id)');
        $this->addSql('ALTER TABLE sales ADD CONSTRAINT fk_sales_session_branch FOREIGN KEY (cash_session_id, branch_id) REFERENCES cash_sessions (id, branch_id)');

        // Enforce returns.branch_id == warehouse.local_id and optional session branch alignment.
        $this->addSql('ALTER TABLE returns ADD CONSTRAINT fk_returns_warehouse_branch FOREIGN KEY (warehouse_id, branch_id) REFERENCES almacen (almacen_id, local_id)');
        $this->addSql('ALTER TABLE returns ADD CONSTRAINT fk_returns_session_branch FOREIGN KEY (cash_session_id, branch_id) REFERENCES cash_sessions (id, branch_id)');

        // Only one OPEN cash session per register.
        $this->addSql("ALTER TABLE cash_sessions ADD COLUMN open_register_guard INT GENERATED ALWAYS AS (CASE WHEN status = 'OPEN' THEN cash_register_id ELSE NULL END) STORED");
        $this->addSql('CREATE UNIQUE INDEX uq_cash_sessions_one_open_per_register ON cash_sessions (open_register_guard)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uq_cash_sessions_one_open_per_register ON cash_sessions');
        $this->addSql('ALTER TABLE cash_sessions DROP COLUMN open_register_guard');

        $this->addSql('ALTER TABLE returns DROP FOREIGN KEY fk_returns_session_branch');
        $this->addSql('ALTER TABLE returns DROP FOREIGN KEY fk_returns_warehouse_branch');

        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY fk_sales_session_branch');
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY fk_sales_register_branch');
        $this->addSql('ALTER TABLE sales DROP FOREIGN KEY fk_sales_warehouse_branch');

        $this->addSql('DROP INDEX uq_cash_sessions_id_branch ON cash_sessions');
        $this->addSql('DROP INDEX uq_cash_register_id_branch ON cash_register');
        $this->addSql('DROP INDEX uq_almacen_id_local ON almacen');

        $this->addSql('DROP INDEX uq_tax_document_number_branch ON tax_document');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TAX_DOCUMENT_NUMBER ON tax_document (document_type, series, number)');

        $this->addSql('DROP INDEX uq_local_comprobante_branch_type_series ON local_comprobante');
    }
}
