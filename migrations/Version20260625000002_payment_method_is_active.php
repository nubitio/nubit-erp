<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds is_active column to payment_method table.
 * All existing methods default to active = true.
 */
final class Version20260625000002_payment_method_is_active extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active flag to payment_method table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE payment_method ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_method DROP COLUMN is_active');
    }
}
