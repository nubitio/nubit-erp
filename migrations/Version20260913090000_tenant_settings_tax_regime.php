<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260913090000_tenant_settings_tax_regime extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the tenant tax regime (GENERAL/NRUS) setting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenant_settings ADD tax_regime VARCHAR(20) DEFAULT 'GENERAL' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings DROP tax_regime');
    }
}
