<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819180000_payroll_liquidacion extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planilla: quinta categoría retention on payslip_entry, LIQUIDACION breakdown columns '
            . '(vacaciones/gratificación/CTS truncas), and employee.last_vacation_taken_at — purely additive';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE employee
            ADD last_vacation_taken_at DATE DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE payslip_entry
            ADD quinta_categoria_retention NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            ADD vacation_trunca_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            ADD gratificacion_trunca_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            ADD cts_trunca_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE payslip_entry
            DROP quinta_categoria_retention,
            DROP vacation_trunca_amount,
            DROP gratificacion_trunca_amount,
            DROP cts_trunca_amount
        SQL);

        $this->addSql('ALTER TABLE employee DROP last_vacation_taken_at');
    }
}
