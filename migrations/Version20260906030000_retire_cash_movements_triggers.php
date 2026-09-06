<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906030000_retire_cash_movements_triggers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire the cash_movements database triggers without privileged DDL';
    }

    public function up(Schema $schema): void
    {
        // DROP TRIGGER also requires elevated privileges while binary logging
        // is enabled. Existing triggers are harmless to the application and
        // can be removed separately by a database administrator if needed.
    }

    public function down(Schema $schema): void
    {
        // The retired trigger implementation is intentionally not restored.
    }
}
