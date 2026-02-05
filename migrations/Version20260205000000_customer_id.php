<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205000000_customer_id extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce customer integer primary key and migrate references from RUC/DNI.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cliente ADD id INT NOT NULL AUTO_INCREMENT UNIQUE');

        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107ADE734E51');
        $this->addSql('ALTER TABLE comprobante DROP FOREIGN KEY FK_55DEEE82DE734E51');
        $this->addSql('ALTER TABLE tax_document DROP FOREIGN KEY FK_TAX_DOCUMENT_CUSTOMER');

        $this->addSql('UPDATE movimiento m INNER JOIN cliente c ON c.ruc = m.cliente_id SET m.cliente_id = c.id');
        $this->addSql('UPDATE comprobante co INNER JOIN cliente c ON c.ruc = co.cliente_id SET co.cliente_id = c.id');
        $this->addSql('UPDATE tax_document td INNER JOIN cliente c ON c.ruc = td.customer_id SET td.customer_id = c.id');

        $this->addSql('ALTER TABLE movimiento MODIFY cliente_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE comprobante MODIFY cliente_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tax_document MODIFY customer_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE cliente DROP PRIMARY KEY');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CLIENTE_RUC ON cliente (ruc)');
        $this->addSql('ALTER TABLE cliente ADD PRIMARY KEY (id)');

        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107ADE734E51 FOREIGN KEY (cliente_id) REFERENCES cliente (id)');
        $this->addSql('ALTER TABLE comprobante ADD CONSTRAINT FK_55DEEE82DE734E51 FOREIGN KEY (cliente_id) REFERENCES cliente (id)');
        $this->addSql('ALTER TABLE tax_document ADD CONSTRAINT FK_TAX_DOCUMENT_CUSTOMER FOREIGN KEY (customer_id) REFERENCES cliente (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107ADE734E51');
        $this->addSql('ALTER TABLE comprobante DROP FOREIGN KEY FK_55DEEE82DE734E51');
        $this->addSql('ALTER TABLE tax_document DROP FOREIGN KEY FK_TAX_DOCUMENT_CUSTOMER');

        $this->addSql('UPDATE movimiento m INNER JOIN cliente c ON c.id = m.cliente_id SET m.cliente_id = c.ruc');
        $this->addSql('UPDATE comprobante co INNER JOIN cliente c ON c.id = co.cliente_id SET co.cliente_id = c.ruc');
        $this->addSql('UPDATE tax_document td INNER JOIN cliente c ON c.id = td.customer_id SET td.customer_id = c.ruc');

        $this->addSql('ALTER TABLE movimiento MODIFY cliente_id VARCHAR(11) DEFAULT NULL');
        $this->addSql('ALTER TABLE comprobante MODIFY cliente_id VARCHAR(11) DEFAULT NULL');
        $this->addSql('ALTER TABLE tax_document MODIFY customer_id VARCHAR(11) DEFAULT NULL');

        $this->addSql('ALTER TABLE cliente DROP PRIMARY KEY');
        $this->addSql('DROP INDEX UNIQ_CLIENTE_RUC ON cliente');
        $this->addSql('ALTER TABLE cliente DROP id');
        $this->addSql('ALTER TABLE cliente ADD PRIMARY KEY (ruc)');

        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107ADE734E51 FOREIGN KEY (cliente_id) REFERENCES cliente (ruc)');
        $this->addSql('ALTER TABLE comprobante ADD CONSTRAINT FK_55DEEE82DE734E51 FOREIGN KEY (cliente_id) REFERENCES cliente (ruc)');
        $this->addSql('ALTER TABLE tax_document ADD CONSTRAINT FK_TAX_DOCUMENT_CUSTOMER FOREIGN KEY (customer_id) REFERENCES cliente (ruc)');
    }
}
