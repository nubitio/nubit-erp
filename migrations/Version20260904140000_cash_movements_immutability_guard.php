<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904140000_cash_movements_immutability_guard extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retired: cash_movements append-only invariant is enforced at the application layer';
    }

    public function up(Schema $schema): void
    {
        // This migration originally created MariaDB triggers. It is kept as
        // a no-op because some tenants may already have recorded the version
        // while others are still pending it. The following migration removes
        // any triggers left by that earlier implementation.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_cash_movements_no_update');
        $this->addSql('DROP TRIGGER IF EXISTS trg_cash_movements_no_delete');
    }
}
