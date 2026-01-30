<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260117000000_detraccion_support extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SUNAT catalog table and detraccion fields for sales and invoices';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sunat_catalog (id INT AUTO_INCREMENT NOT NULL, catalog INT NOT NULL, code VARCHAR(10) NOT NULL, description VARCHAR(255) NOT NULL, percentage DOUBLE PRECISION DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_sunat_catalog_code (catalog, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE movimiento ADD tipo_operacion VARCHAR(4) DEFAULT NULL, ADD detraccion_codigo VARCHAR(10) DEFAULT NULL, ADD detraccion_porcentaje DOUBLE PRECISION DEFAULT NULL, ADD detraccion_monto DOUBLE PRECISION DEFAULT NULL, ADD detraccion_medio_pago VARCHAR(10) DEFAULT NULL, ADD detraccion_valor_referencial DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE comprobante ADD tipo_operacion VARCHAR(4) DEFAULT NULL, ADD detraccion_codigo VARCHAR(10) DEFAULT NULL, ADD detraccion_porcentaje DOUBLE PRECISION DEFAULT NULL, ADD detraccion_monto DOUBLE PRECISION DEFAULT NULL, ADD detraccion_medio_pago VARCHAR(10) DEFAULT NULL, ADD detraccion_valor_referencial DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE producto ADD detraccion_catalog_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE producto ADD CONSTRAINT FK_5C0C021E3E4EDC61 FOREIGN KEY (detraccion_catalog_id) REFERENCES sunat_catalog (id)');
        $this->addSql('CREATE INDEX IDX_5C0C021E3E4EDC61 ON producto (detraccion_catalog_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE movimiento DROP tipo_operacion, DROP detraccion_codigo, DROP detraccion_porcentaje, DROP detraccion_monto, DROP detraccion_medio_pago, DROP detraccion_valor_referencial');
        $this->addSql('ALTER TABLE comprobante DROP tipo_operacion, DROP detraccion_codigo, DROP detraccion_porcentaje, DROP detraccion_monto, DROP detraccion_medio_pago, DROP detraccion_valor_referencial');
        $this->addSql('ALTER TABLE producto DROP FOREIGN KEY FK_5C0C021E3E4EDC61');
        $this->addSql('DROP INDEX IDX_5C0C021E3E4EDC61 ON producto');
        $this->addSql('ALTER TABLE producto DROP detraccion_catalog_id');
        $this->addSql('DROP TABLE sunat_catalog');
    }
}
