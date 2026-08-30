<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828090000_cash_rounding_setting extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the per-tenant cash rounding switch for POS sales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings ADD cash_rounding_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings DROP cash_rounding_enabled');
    }
}
