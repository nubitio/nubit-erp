<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913091000_sales_requested_voucher_type extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the voucher type explicitly requested by the cashier for a sale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales ADD requested_voucher_type VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP requested_voucher_type');
    }
}
