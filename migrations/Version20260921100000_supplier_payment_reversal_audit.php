<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921100000_supplier_payment_reversal_audit extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retain supplier payment rows and mark reversals for an append-only audit trail.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_payment ADD reversed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_payment DROP reversed_at');
    }
}
