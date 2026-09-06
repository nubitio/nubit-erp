<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906020000_receivable_account_history extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retain receivable accounts after full collection and backfill existing debts and credit due dates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales ADD receivable_account TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE sales SET receivable_account = 1 WHERE balance_due > 0 OR due_date IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP COLUMN receivable_account');
    }
}
