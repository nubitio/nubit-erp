<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260202000000_branch_filter extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add branch_id column to movimiento, comprobante, nota, cash_shift and cash_movement tables for branch-level filtering';
    }

    public function up(Schema $schema): void
    {
        // Add branch_id to movimiento (Sale) table
        $this->addSql('ALTER TABLE movimiento ADD branch_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_388E576ADCD6CC49 FOREIGN KEY (branch_id) REFERENCES `local` (local_id)');
        $this->addSql('CREATE INDEX IDX_388E576ADCD6CC49 ON movimiento (branch_id)');

        // Add branch_id to comprobante (Invoice) table
        $this->addSql('ALTER TABLE comprobante ADD branch_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE comprobante ADD CONSTRAINT FK_6682A859DCD6CC49 FOREIGN KEY (branch_id) REFERENCES `local` (local_id)');
        $this->addSql('CREATE INDEX IDX_6682A859DCD6CC49 ON comprobante (branch_id)');

        // Add branch_id to nota (CreditNote) table
        $this->addSql('ALTER TABLE nota ADD branch_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE nota ADD CONSTRAINT FK_C8D03E0DDCD6CC49 FOREIGN KEY (branch_id) REFERENCES `local` (local_id)');
        $this->addSql('CREATE INDEX IDX_C8D03E0DDCD6CC49 ON nota (branch_id)');

        // Add branch_id to cash_shift table
        $this->addSql('ALTER TABLE cash_shift ADD branch_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cash_shift ADD CONSTRAINT FK_A5C5C6DCDCD6CC49 FOREIGN KEY (branch_id) REFERENCES `local` (local_id)');
        $this->addSql('CREATE INDEX IDX_A5C5C6DCDCD6CC49 ON cash_shift (branch_id)');

        // Add branch_id to cash_movement table
        $this->addSql('ALTER TABLE cash_movement ADD branch_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_B2D25EBDCD6CC49 FOREIGN KEY (branch_id) REFERENCES `local` (local_id)');
        $this->addSql('CREATE INDEX IDX_B2D25EBDCD6CC49 ON cash_movement (branch_id)');

        // Populate branch_id for existing sales based on almacen relationship
        $this->addSql('
            UPDATE movimiento m
            INNER JOIN almacen a ON a.almacen_id = m.almacen_id
            SET m.branch_id = a.local_id
            WHERE m.branch_id IS NULL
        ');

        // Populate branch_id for existing invoices based on sale's branch
        $this->addSql('
            UPDATE comprobante c
            INNER JOIN movimiento m ON m.movimiento_id = c.movimiento_id
            SET c.branch_id = m.branch_id
            WHERE c.branch_id IS NULL
        ');

        // Populate branch_id for existing credit notes based on sale's branch
        $this->addSql('
            UPDATE nota n
            INNER JOIN movimiento m ON m.movimiento_id = n.movimiento_id
            SET n.branch_id = m.branch_id
            WHERE n.branch_id IS NULL AND m.branch_id IS NOT NULL
        ');

        // Populate branch_id for existing cash_shifts based on cash_register's branch
        $this->addSql('
            UPDATE cash_shift cs
            INNER JOIN cash_register cr ON cr.id = cs.cash_register_id
            SET cs.branch_id = cr.branch_id
            WHERE cs.branch_id IS NULL AND cr.branch_id IS NOT NULL
        ');

        // Populate branch_id for existing cash_movements based on cash_shift's branch
        $this->addSql('
            UPDATE cash_movement cm
            INNER JOIN cash_shift cs ON cs.id = cm.cash_shift_id
            SET cm.branch_id = cs.branch_id
            WHERE cm.branch_id IS NULL AND cs.branch_id IS NOT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // Drop from movimiento
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_388E576ADCD6CC49');
        $this->addSql('DROP INDEX IDX_388E576ADCD6CC49 ON movimiento');
        $this->addSql('ALTER TABLE movimiento DROP branch_id');

        // Drop from comprobante
        $this->addSql('ALTER TABLE comprobante DROP FOREIGN KEY FK_6682A859DCD6CC49');
        $this->addSql('DROP INDEX IDX_6682A859DCD6CC49 ON comprobante');
        $this->addSql('ALTER TABLE comprobante DROP branch_id');

        // Drop from nota
        $this->addSql('ALTER TABLE nota DROP FOREIGN KEY FK_C8D03E0DDCD6CC49');
        $this->addSql('DROP INDEX IDX_C8D03E0DDCD6CC49 ON nota');
        $this->addSql('ALTER TABLE nota DROP branch_id');

        // Drop from cash_shift
        $this->addSql('ALTER TABLE cash_shift DROP FOREIGN KEY FK_A5C5C6DCDCD6CC49');
        $this->addSql('DROP INDEX IDX_A5C5C6DCDCD6CC49 ON cash_shift');
        $this->addSql('ALTER TABLE cash_shift DROP branch_id');

        // Drop from cash_movement
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_B2D25EBDCD6CC49');
        $this->addSql('DROP INDEX IDX_B2D25EBDCD6CC49 ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement DROP branch_id');
    }
}
