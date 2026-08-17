<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260817120000_expense_reports extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expense reports (viáticos): employee out-of-pocket expense claims with an '
            . 'approve/reject/reimburse review workflow, purely additive — no existing table changed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE expense_report (
                id CHAR(36) NOT NULL,
                employee_id INT NOT NULL,
                category_id INT NOT NULL,
                branch_id CHAR(36) NOT NULL,
                description VARCHAR(255) NOT NULL,
                amount NUMERIC(12, 2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'PEN' NOT NULL,
                expense_date DATE NOT NULL,
                receipt_media_id CHAR(36) DEFAULT NULL,
                status VARCHAR(20) DEFAULT 'PENDING' NOT NULL,
                reviewed_by_id INT DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                review_note LONGTEXT DEFAULT NULL,
                reimbursed_at DATETIME DEFAULT NULL,
                note LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_expense_report_status (status),
                INDEX idx_expense_report_employee (employee_id),
                INDEX IDX_EXPENSE_REPORT_CATEGORY (category_id),
                INDEX IDX_EXPENSE_REPORT_BRANCH (branch_id),
                INDEX IDX_EXPENSE_REPORT_RECEIPT_MEDIA (receipt_media_id),
                INDEX IDX_EXPENSE_REPORT_REVIEWED_BY (reviewed_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE expense_report ADD CONSTRAINT FK_EXPENSE_REPORT_EMPLOYEE FOREIGN KEY (employee_id) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE expense_report ADD CONSTRAINT FK_EXPENSE_REPORT_CATEGORY FOREIGN KEY (category_id) REFERENCES expense_category (id)');
        $this->addSql('ALTER TABLE expense_report ADD CONSTRAINT FK_EXPENSE_REPORT_BRANCH FOREIGN KEY (branch_id) REFERENCES local (local_id)');
        $this->addSql('ALTER TABLE expense_report ADD CONSTRAINT FK_EXPENSE_REPORT_RECEIPT_MEDIA FOREIGN KEY (receipt_media_id) REFERENCES nubit_media (id)');
        $this->addSql('ALTER TABLE expense_report ADD CONSTRAINT FK_EXPENSE_REPORT_REVIEWED_BY FOREIGN KEY (reviewed_by_id) REFERENCES usuario (usuario_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE expense_report');
    }
}
