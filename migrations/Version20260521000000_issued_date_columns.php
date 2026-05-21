<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521000000_issued_date_columns extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add sales.issued_date (DATE, Lima) and convert tax_document.issue_date from DATETIME to DATE';
    }

    public function up(Schema $schema): void
    {
        // ── sales: add issued_date DATE column ────────────────────────────────
        // Backfill from issued_at: for midnight-UTC rows (from date-only strings)
        // trust the UTC date directly; for real timestamps convert to Lima.
        $this->addSql('ALTER TABLE sales ADD COLUMN issued_date DATE NULL AFTER issued_at');
        $this->addSql(<<<'SQL'
            UPDATE sales
            SET issued_date = CASE
                WHEN TIME(issued_at) = '00:00:00' THEN DATE(issued_at)
                ELSE DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima'))
            END
            WHERE issued_at IS NOT NULL
        SQL);
        $this->addSql(
            'CREATE INDEX idx_sales_issued_date_branch ON sales (issued_date, branch_id, status)'
        );

        // ── tax_document: DATETIME → DATE ────────────────────────────────────
        // Convert stored UTC datetimes to Lima dates in a new column, then swap.
        $this->addSql('ALTER TABLE tax_document ADD COLUMN issue_date_new DATE NULL');
        $this->addSql(<<<'SQL'
            UPDATE tax_document
            SET issue_date_new = CASE
                WHEN TIME(issue_date) = '00:00:00' THEN DATE(issue_date)
                ELSE DATE(CONVERT_TZ(issue_date, '+00:00', 'America/Lima'))
            END
            WHERE issue_date IS NOT NULL
        SQL);
        $this->addSql('ALTER TABLE tax_document DROP COLUMN issue_date');
        $this->addSql('ALTER TABLE tax_document CHANGE issue_date_new issue_date DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // sales: drop issued_date
        $this->addSql('DROP INDEX idx_sales_issued_date_branch ON sales');
        $this->addSql('ALTER TABLE sales DROP COLUMN issued_date');

        // tax_document: restore DATETIME (data loss for time component is acceptable)
        $this->addSql('ALTER TABLE tax_document MODIFY issue_date DATETIME NOT NULL');
    }
}
