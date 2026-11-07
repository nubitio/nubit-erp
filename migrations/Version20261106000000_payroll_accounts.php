<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106000000_payroll_accounts extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chart-of-accounts entries used by payroll accrual and payment postings.';
    }

    public function up(Schema $schema): void
    {
        // code, name, type, is_header, parent_code
        $accounts = [
            ['4017', 'Impuesto a la renta - quinta categoría', 'LIABILITY', 0, '40'],
            ['4031', 'EsSalud', 'LIABILITY', 0, '40'],
            ['4032', 'ONP', 'LIABILITY', 0, '40'],
            ['41', 'Remuneraciones y participaciones por pagar', 'LIABILITY', 1, null],
            ['411', 'Remuneraciones por pagar', 'LIABILITY', 0, '41'],
            ['407', 'Administradoras de fondos de pensiones', 'LIABILITY', 0, '40'],
            ['62', 'Gastos de personal', 'EXPENSE', 1, null],
            ['621', 'Remuneraciones', 'EXPENSE', 0, '62'],
            ['622', 'Otras remuneraciones', 'EXPENSE', 0, '62'],
            ['627', 'Seguridad, previsión social y otros', 'EXPENSE', 0, '62'],
        ];

        foreach ($accounts as [$code, $name, $type, $header, $parent]) {
            $parentSql = $parent === null ? 'NULL' : $this->connection->quote($parent);
            $this->addSql(sprintf(
                'INSERT INTO chart_of_accounts (id, code, name, type, is_header, parent_code, is_active) '
                . 'SELECT UUID(), %1$s, %2$s, %3$s, %4$d, %5$s, 1 FROM DUAL '
                . 'WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE code = %1$s)',
                $this->connection->quote($code),
                $this->connection->quote($name),
                $this->connection->quote($type),
                $header,
                $parentSql,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM chart_of_accounts WHERE code IN ('4017','4031','4032','411','41','407','621','622','627','62') "
            . 'AND id NOT IN (SELECT account_id FROM journal_entry_line)',
        );
    }
}
