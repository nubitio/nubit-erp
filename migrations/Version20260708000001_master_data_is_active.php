<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708000001_master_data_is_active extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active flag to master-data catalogs (customers, suppliers, warehouses, '
            . 'categories, voucher series/types, units of measure, cash registers) so they can be '
            . 'disabled without breaking historical references';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cliente ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE proveedor ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE almacen ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE categoria ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE local_comprobante ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE tipo_comprobante ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE unidad ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE cash_register ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cliente DROP COLUMN is_active');
        $this->addSql('ALTER TABLE proveedor DROP COLUMN is_active');
        $this->addSql('ALTER TABLE almacen DROP COLUMN is_active');
        $this->addSql('ALTER TABLE categoria DROP COLUMN is_active');
        $this->addSql('ALTER TABLE local_comprobante DROP COLUMN is_active');
        $this->addSql('ALTER TABLE tipo_comprobante DROP COLUMN is_active');
        $this->addSql('ALTER TABLE unidad DROP COLUMN is_active');
        $this->addSql('ALTER TABLE cash_register DROP COLUMN is_active');
    }
}
