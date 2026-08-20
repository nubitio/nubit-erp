<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819120000_payroll extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planilla: employees, AFP rate reference table, payroll runs, and payslip entries '
            . '(regular monthly, gratificaciones, CTS) — purely additive, no existing table changed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE employee (
                id CHAR(36) NOT NULL,
                company_id VARCHAR(255) NOT NULL,
                user_id INT DEFAULT NULL,
                dni VARCHAR(8) NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                hire_date DATE NOT NULL,
                termination_date DATE DEFAULT NULL,
                contract_type VARCHAR(20) DEFAULT 'INDEFINIDO' NOT NULL,
                base_salary NUMERIC(12, 2) NOT NULL,
                pension_scheme VARCHAR(10) DEFAULT 'ONP' NOT NULL,
                afp_provider VARCHAR(20) DEFAULT NULL,
                eps_affiliated TINYINT(1) DEFAULT 0 NOT NULL,
                status VARCHAR(20) DEFAULT 'ACTIVE' NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX uniq_employee_company_dni (company_id, dni),
                INDEX idx_employee_status (status),
                INDEX IDX_EMPLOYEE_COMPANY (company_id),
                INDEX IDX_EMPLOYEE_USER (user_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE afp_rate (
                id INT AUTO_INCREMENT NOT NULL,
                afp_provider VARCHAR(20) NOT NULL,
                mandatory_contribution_rate NUMERIC(5, 2) NOT NULL,
                insurance_premium_rate NUMERIC(5, 2) NOT NULL,
                commission_rate NUMERIC(5, 2) NOT NULL,
                effective_from DATE NOT NULL,
                note LONGTEXT DEFAULT NULL,
                INDEX idx_afp_rate_provider (afp_provider),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE payroll_run (
                id CHAR(36) NOT NULL,
                company_id VARCHAR(255) NOT NULL,
                period DATE NOT NULL,
                type VARCHAR(30) DEFAULT 'REGULAR' NOT NULL,
                status VARCHAR(20) DEFAULT 'DRAFT' NOT NULL,
                calculated_at DATETIME DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                paid_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_payroll_run_status (status),
                INDEX idx_payroll_run_company (company_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE payslip_entry (
                id CHAR(36) NOT NULL,
                payroll_run_id CHAR(36) NOT NULL,
                employee_id CHAR(36) NOT NULL,
                gross_salary NUMERIC(12, 2) NOT NULL,
                afp_mandatory_contribution NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                afp_insurance_premium NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                afp_commission NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                onp_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                essalud_employer_contribution NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                bonificacion_extraordinaria NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                net_pay NUMERIC(12, 2) NOT NULL,
                note LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_payslip_entry_payroll_run (payroll_run_id),
                INDEX idx_payslip_entry_employee (employee_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE employee ADD CONSTRAINT FK_EMPLOYEE_COMPANY FOREIGN KEY (company_id) REFERENCES empresa (empresa_id)');
        $this->addSql('ALTER TABLE employee ADD CONSTRAINT FK_EMPLOYEE_USER FOREIGN KEY (user_id) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE payroll_run ADD CONSTRAINT FK_PAYROLL_RUN_COMPANY FOREIGN KEY (company_id) REFERENCES empresa (empresa_id)');
        $this->addSql('ALTER TABLE payslip_entry ADD CONSTRAINT FK_PAYSLIP_ENTRY_PAYROLL_RUN FOREIGN KEY (payroll_run_id) REFERENCES payroll_run (id)');
        $this->addSql('ALTER TABLE payslip_entry ADD CONSTRAINT FK_PAYSLIP_ENTRY_EMPLOYEE FOREIGN KEY (employee_id) REFERENCES employee (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payslip_entry');
        $this->addSql('DROP TABLE payroll_run');
        $this->addSql('DROP TABLE afp_rate');
        $this->addSql('DROP TABLE employee');
    }
}
