<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205023010_initial extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE almacen (almacen_id VARCHAR(255) NOT NULL, local_id VARCHAR(255) DEFAULT NULL, descripcion VARCHAR(255) NOT NULL, INDEX IDX_D5B2D2505D5A2101 (local_id), PRIMARY KEY(almacen_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cash_movement (id INT AUTO_INCREMENT NOT NULL, cash_shift_id INT NOT NULL, category_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, amount DOUBLE PRECISION NOT NULL, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_965E643AD9F34DC3 (cash_shift_id), INDEX IDX_965E643A12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cash_movement_category (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(20) NOT NULL, is_system TINYINT(1) DEFAULT 1 NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_7FE7DF1877153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cash_register (id INT AUTO_INCREMENT NOT NULL, branch_id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, INDEX IDX_3D7AB1D9DCD6CC49 (branch_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cash_shift (id INT AUTO_INCREMENT NOT NULL, cash_register_id INT NOT NULL, opened_by INT NOT NULL, closed_by INT DEFAULT NULL, status VARCHAR(255) NOT NULL, opening_balance DOUBLE PRECISION NOT NULL, closing_balance DOUBLE PRECISION DEFAULT NULL, opened_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', closed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expected_amount DOUBLE PRECISION DEFAULT NULL, counted_amount DOUBLE PRECISION DEFAULT NULL, difference DOUBLE PRECISION DEFAULT NULL, note LONGTEXT DEFAULT NULL, INDEX IDX_9635A069A917CC69 (cash_register_id), INDEX IDX_9635A069DE97F77A (opened_by), INDEX IDX_9635A06988F6E01 (closed_by), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE categoria (categoria_id VARCHAR(255) NOT NULL, parent_id VARCHAR(255) DEFAULT NULL, descripcion VARCHAR(255) NOT NULL, INDEX IDX_4E10122D727ACA70 (parent_id), PRIMARY KEY(categoria_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cliente (ruc VARCHAR(11) NOT NULL, price_list_id INT DEFAULT NULL, razon_social VARCHAR(255) NOT NULL, domicilio_fiscal VARCHAR(255) DEFAULT NULL, telefono VARCHAR(20) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, INDEX IDX_F41C9B255688DED7 (price_list_id), PRIMARY KEY(ruc)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE comprobante (comprobante_id VARCHAR(255) NOT NULL, movimiento_id VARCHAR(255) NOT NULL, cliente_id VARCHAR(11) DEFAULT NULL, raw_document_id INT DEFAULT NULL, tipo_comprobante_id VARCHAR(255) NOT NULL, fecha DATETIME NOT NULL, serie VARCHAR(255) NOT NULL, numero VARCHAR(255) NOT NULL, inafecto TINYINT(1) NOT NULL, subtotal DOUBLE PRECISION NOT NULL, gravada DOUBLE PRECISION NOT NULL, igv DOUBLE PRECISION NOT NULL, rc DOUBLE PRECISION NOT NULL, total DOUBLE PRECISION NOT NULL, discount_percent INT NOT NULL, discount_amount DOUBLE PRECISION NOT NULL, voided INT NOT NULL, digest VARCHAR(255) DEFAULT NULL, ticket_baja VARCHAR(255) DEFAULT NULL, correlativo_baja VARCHAR(255) DEFAULT NULL, envio_sunat VARCHAR(255) DEFAULT NULL, mensaje_sunat LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX IDX_55DEEE8258E7D5A2 (movimiento_id), INDEX IDX_55DEEE82DE734E51 (cliente_id), PRIMARY KEY(comprobante_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE config (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE empresa (empresa_id VARCHAR(255) NOT NULL, logo VARCHAR(255) DEFAULT NULL, ruc VARCHAR(11) NOT NULL, razon_social VARCHAR(255) NOT NULL, nombre_comercial VARCHAR(255) DEFAULT NULL, domicilio_fiscal VARCHAR(255) NOT NULL, telefono VARCHAR(10) DEFAULT NULL, ubigeo VARCHAR(255) DEFAULT NULL, departamento VARCHAR(255) DEFAULT NULL, provincia VARCHAR(255) DEFAULT NULL, distrito VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_B8D75A50E48E9A13 (logo), PRIMARY KEY(empresa_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE local (local_id VARCHAR(255) NOT NULL, empresa_id VARCHAR(255) NOT NULL, descripcion VARCHAR(255) NOT NULL, direccion VARCHAR(255) DEFAULT NULL, telefono VARCHAR(255) DEFAULT NULL, INDEX IDX_8BD688E8521E1991 (empresa_id), PRIMARY KEY(local_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE local_comprobante (id INT AUTO_INCREMENT NOT NULL, local_id VARCHAR(255) NOT NULL, tipo_comprobante_id VARCHAR(2) NOT NULL, serie VARCHAR(6) NOT NULL, serie_asociada VARCHAR(6) DEFAULT NULL, INDEX IDX_3ED79C2C5D5A2101 (local_id), INDEX IDX_3ED79C2CA9B5E49A (tipo_comprobante_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media (id VARCHAR(255) NOT NULL, path VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, key_name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, icon VARCHAR(255) DEFAULT NULL, route VARCHAR(255) DEFAULT NULL, external_url VARCHAR(255) DEFAULT NULL, order_index INT NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_7D053A93727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE menu_rol (id INT AUTO_INCREMENT NOT NULL, menu_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_96F4F105CCD7E912 (menu_id), INDEX IDX_96F4F105D60322AC (role_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE movimiento (movimiento_id VARCHAR(255) NOT NULL, operacion_id VARCHAR(255) DEFAULT NULL, almacen_id VARCHAR(255) DEFAULT NULL, cliente_id VARCHAR(11) DEFAULT NULL, price_list_id INT DEFAULT NULL, proveedor_id VARCHAR(255) DEFAULT NULL, tipo_pago INT DEFAULT NULL, tipo_id INT NOT NULL, fecha DATETIME NOT NULL, forma_pago INT DEFAULT NULL, tipo_tarjeta INT DEFAULT NULL, serie VARCHAR(255) DEFAULT NULL, numero VARCHAR(255) DEFAULT NULL, descuento DOUBLE PRECISION DEFAULT NULL, glosa VARCHAR(255) DEFAULT NULL, orden_id INT DEFAULT NULL, has_invoice TINYINT(1) DEFAULT 0 NOT NULL, is_consume TINYINT(1) DEFAULT 0 NOT NULL, caja_id INT DEFAULT NULL, estado INT DEFAULT 1 NOT NULL, date_created DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, date_updated DATETIME DEFAULT NULL, created_by INT NOT NULL, updated_by INT DEFAULT NULL, igv INT DEFAULT NULL, recargo_consumo INT DEFAULT NULL, invoice VARCHAR(255) DEFAULT NULL, subtotal DOUBLE PRECISION NOT NULL, total DOUBLE PRECISION NOT NULL, INDEX IDX_C8FF107AE6D597C3 (operacion_id), INDEX IDX_C8FF107A9C9C9E68 (almacen_id), INDEX IDX_C8FF107ADE734E51 (cliente_id), INDEX IDX_C8FF107A5688DED7 (price_list_id), INDEX IDX_C8FF107ACB305D73 (proveedor_id), INDEX IDX_C8FF107AFEF0887B (tipo_pago), PRIMARY KEY(movimiento_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE movimiento_detalle (id INT AUTO_INCREMENT NOT NULL, movimiento_id VARCHAR(255) DEFAULT NULL, producto_id VARCHAR(255) DEFAULT NULL, cantidad DOUBLE PRECISION NOT NULL, precio_unitario DOUBLE PRECISION DEFAULT NULL, descuento DOUBLE PRECISION NOT NULL, precio_total DOUBLE PRECISION DEFAULT NULL, INDEX IDX_939E840D58E7D5A2 (movimiento_id), INDEX IDX_939E840D7645698E (producto_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE nota (nota_id VARCHAR(36) NOT NULL, movimiento_id VARCHAR(255) NOT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, raw_document_id INT DEFAULT NULL, fecha DATE NOT NULL, tipo_id VARCHAR(10) NOT NULL, codigo_motivo VARCHAR(10) NOT NULL, descripcion_motivo VARCHAR(255) NOT NULL, serie VARCHAR(10) NOT NULL, correlativo INT NOT NULL, doc_afectado VARCHAR(50) DEFAULT NULL, digest VARCHAR(255) DEFAULT NULL, envio_sunat VARCHAR(50) DEFAULT NULL, mensaje_sunat LONGTEXT DEFAULT NULL, total NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_C8D03E0D58E7D5A2 (movimiento_id), INDEX IDX_C8D03E0DDE12AB56 (created_by), INDEX IDX_C8D03E0D16FE72E1 (updated_by), PRIMARY KEY(nota_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE operacion (operacion_id VARCHAR(255) NOT NULL, descripcion VARCHAR(255) NOT NULL, tipo_id INT NOT NULL, PRIMARY KEY(operacion_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment_method (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE price_list (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product_stock (id INT AUTO_INCREMENT NOT NULL, product_id VARCHAR(255) NOT NULL, warehouse_id VARCHAR(255) NOT NULL, stock DECIMAL(12,3) NOT NULL, min_stock DECIMAL(12,3) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EA6A2D3C4584665A (product_id), INDEX IDX_EA6A2D3C5080ECDE (warehouse_id), UNIQUE INDEX product_warehouse_unique (product_id, warehouse_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE producto (producto_id VARCHAR(255) NOT NULL, categoria_id VARCHAR(255) NOT NULL, unidad_id INT NOT NULL, imagen VARCHAR(255) DEFAULT NULL, codigo VARCHAR(255) DEFAULT NULL, tipo_id INT NOT NULL, descripcion VARCHAR(255) NOT NULL, precio_compra DOUBLE PRECISION DEFAULT NULL, precio DOUBLE PRECISION NOT NULL, stock DECIMAL(12,3) DEFAULT NULL, stock_minimo DECIMAL(12,3) DEFAULT NULL, INDEX IDX_A7BB06153397707A (categoria_id), INDEX IDX_A7BB06159D01464C (unidad_id), INDEX IDX_A7BB06158319D2B3 (imagen), PRIMARY KEY(producto_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE proveedor (proveedor_id VARCHAR(255) NOT NULL, ruc VARCHAR(11) NOT NULL, razon_social VARCHAR(255) NOT NULL, domicilio_fiscal VARCHAR(255) DEFAULT NULL, telefono VARCHAR(14) DEFAULT NULL, PRIMARY KEY(proveedor_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rol (rol_id INT AUTO_INCREMENT NOT NULL, descripcion VARCHAR(255) NOT NULL, PRIMARY KEY(rol_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE summary (id INT AUTO_INCREMENT NOT NULL, date DATE DEFAULT NULL, ticket VARCHAR(255) DEFAULT NULL, correlative VARCHAR(255) DEFAULT NULL, hash VARCHAR(255) DEFAULT NULL, code VARCHAR(255) DEFAULT NULL, msg VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE time_dimension (id INT NOT NULL, date DATE DEFAULT NULL, year INT DEFAULT NULL, month INT DEFAULT NULL, day INT DEFAULT NULL, quarter INT DEFAULT NULL, week INT DEFAULT NULL, day_name VARCHAR(255) DEFAULT NULL, month_name VARCHAR(255) DEFAULT NULL, holiday TINYINT(1) DEFAULT 0, weekend TINYINT(1) DEFAULT 0, event VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tipo_comprobante (codigo VARCHAR(2) NOT NULL, descripcion VARCHAR(255) NOT NULL, PRIMARY KEY(codigo)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ubigeo (codigo VARCHAR(10) NOT NULL, departamento VARCHAR(255) DEFAULT NULL, provincia VARCHAR(255) DEFAULT NULL, distrito VARCHAR(255) DEFAULT NULL, PRIMARY KEY(codigo)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE unidad (unidad_id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(255) NOT NULL, type_id INT NOT NULL, ratio DOUBLE PRECISION NOT NULL, is_reference TINYINT(1) NOT NULL, PRIMARY KEY(unidad_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE usuario (usuario_id INT AUTO_INCREMENT NOT NULL, rol_id INT NOT NULL, username VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, INDEX IDX_2265B05D4BAB96C (rol_id), PRIMARY KEY(usuario_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE almacen ADD CONSTRAINT FK_D5B2D2505D5A2101 FOREIGN KEY (local_id) REFERENCES local (local_id)');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643AD9F34DC3 FOREIGN KEY (cash_shift_id) REFERENCES cash_shift (id)');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643A12469DE2 FOREIGN KEY (category_id) REFERENCES cash_movement_category (id)');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D9DCD6CC49 FOREIGN KEY (branch_id) REFERENCES local (local_id)');
        $this->addSql('ALTER TABLE cash_shift ADD CONSTRAINT FK_9635A069A917CC69 FOREIGN KEY (cash_register_id) REFERENCES cash_register (id)');
        $this->addSql('ALTER TABLE cash_shift ADD CONSTRAINT FK_9635A069DE97F77A FOREIGN KEY (opened_by) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE cash_shift ADD CONSTRAINT FK_9635A06988F6E01 FOREIGN KEY (closed_by) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE categoria ADD CONSTRAINT FK_4E10122D727ACA70 FOREIGN KEY (parent_id) REFERENCES categoria (categoria_id)');
        $this->addSql('ALTER TABLE cliente ADD CONSTRAINT FK_F41C9B255688DED7 FOREIGN KEY (price_list_id) REFERENCES price_list (id)');
        $this->addSql('ALTER TABLE comprobante ADD CONSTRAINT FK_55DEEE8258E7D5A2 FOREIGN KEY (movimiento_id) REFERENCES movimiento (movimiento_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comprobante ADD CONSTRAINT FK_55DEEE82DE734E51 FOREIGN KEY (cliente_id) REFERENCES cliente (ruc)');
        $this->addSql('ALTER TABLE empresa ADD CONSTRAINT FK_B8D75A50E48E9A13 FOREIGN KEY (logo) REFERENCES media (id)');
        $this->addSql('ALTER TABLE local ADD CONSTRAINT FK_8BD688E8521E1991 FOREIGN KEY (empresa_id) REFERENCES empresa (empresa_id)');
        $this->addSql('ALTER TABLE local_comprobante ADD CONSTRAINT FK_3ED79C2C5D5A2101 FOREIGN KEY (local_id) REFERENCES local (local_id)');
        $this->addSql('ALTER TABLE local_comprobante ADD CONSTRAINT FK_3ED79C2CA9B5E49A FOREIGN KEY (tipo_comprobante_id) REFERENCES tipo_comprobante (codigo)');
        $this->addSql('ALTER TABLE menu ADD CONSTRAINT FK_7D053A93727ACA70 FOREIGN KEY (parent_id) REFERENCES menu (id)');
        $this->addSql('ALTER TABLE menu_rol ADD CONSTRAINT FK_96F4F105CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id)');
        $this->addSql('ALTER TABLE menu_rol ADD CONSTRAINT FK_96F4F105D60322AC FOREIGN KEY (role_id) REFERENCES rol (rol_id)');
        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107AE6D597C3 FOREIGN KEY (operacion_id) REFERENCES operacion (operacion_id)');
        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107A9C9C9E68 FOREIGN KEY (almacen_id) REFERENCES almacen (almacen_id)');
        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107ADE734E51 FOREIGN KEY (cliente_id) REFERENCES cliente (ruc)');
        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107A5688DED7 FOREIGN KEY (price_list_id) REFERENCES price_list (id)');
        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107ACB305D73 FOREIGN KEY (proveedor_id) REFERENCES proveedor (proveedor_id)');
        $this->addSql('ALTER TABLE movimiento ADD CONSTRAINT FK_C8FF107AFEF0887B FOREIGN KEY (tipo_pago) REFERENCES payment_method (id)');
        $this->addSql('ALTER TABLE movimiento_detalle ADD CONSTRAINT FK_939E840D58E7D5A2 FOREIGN KEY (movimiento_id) REFERENCES movimiento (movimiento_id)');
        $this->addSql('ALTER TABLE movimiento_detalle ADD CONSTRAINT FK_939E840D7645698E FOREIGN KEY (producto_id) REFERENCES producto (producto_id)');
        $this->addSql('ALTER TABLE nota ADD CONSTRAINT FK_C8D03E0D58E7D5A2 FOREIGN KEY (movimiento_id) REFERENCES movimiento (movimiento_id)');
        $this->addSql('ALTER TABLE nota ADD CONSTRAINT FK_C8D03E0DDE12AB56 FOREIGN KEY (created_by) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE nota ADD CONSTRAINT FK_C8D03E0D16FE72E1 FOREIGN KEY (updated_by) REFERENCES usuario (usuario_id)');
        $this->addSql('ALTER TABLE product_stock ADD CONSTRAINT FK_EA6A2D3C4584665A FOREIGN KEY (product_id) REFERENCES producto (producto_id)');
        $this->addSql('ALTER TABLE product_stock ADD CONSTRAINT FK_EA6A2D3C5080ECDE FOREIGN KEY (warehouse_id) REFERENCES almacen (almacen_id)');
        $this->addSql('ALTER TABLE producto ADD CONSTRAINT FK_A7BB06153397707A FOREIGN KEY (categoria_id) REFERENCES categoria (categoria_id)');
        $this->addSql('ALTER TABLE producto ADD CONSTRAINT FK_A7BB06159D01464C FOREIGN KEY (unidad_id) REFERENCES unidad (unidad_id)');
        $this->addSql('ALTER TABLE producto ADD CONSTRAINT FK_A7BB06158319D2B3 FOREIGN KEY (imagen) REFERENCES media (id)');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D4BAB96C FOREIGN KEY (rol_id) REFERENCES rol (rol_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE almacen DROP FOREIGN KEY FK_D5B2D2505D5A2101');
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643AD9F34DC3');
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643A12469DE2');
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D9DCD6CC49');
        $this->addSql('ALTER TABLE cash_shift DROP FOREIGN KEY FK_9635A069A917CC69');
        $this->addSql('ALTER TABLE cash_shift DROP FOREIGN KEY FK_9635A069DE97F77A');
        $this->addSql('ALTER TABLE cash_shift DROP FOREIGN KEY FK_9635A06988F6E01');
        $this->addSql('ALTER TABLE categoria DROP FOREIGN KEY FK_4E10122D727ACA70');
        $this->addSql('ALTER TABLE cliente DROP FOREIGN KEY FK_F41C9B255688DED7');
        $this->addSql('ALTER TABLE comprobante DROP FOREIGN KEY FK_55DEEE8258E7D5A2');
        $this->addSql('ALTER TABLE comprobante DROP FOREIGN KEY FK_55DEEE82DE734E51');
        $this->addSql('ALTER TABLE empresa DROP FOREIGN KEY FK_B8D75A50E48E9A13');
        $this->addSql('ALTER TABLE local DROP FOREIGN KEY FK_8BD688E8521E1991');
        $this->addSql('ALTER TABLE local_comprobante DROP FOREIGN KEY FK_3ED79C2C5D5A2101');
        $this->addSql('ALTER TABLE local_comprobante DROP FOREIGN KEY FK_3ED79C2CA9B5E49A');
        $this->addSql('ALTER TABLE menu DROP FOREIGN KEY FK_7D053A93727ACA70');
        $this->addSql('ALTER TABLE menu_rol DROP FOREIGN KEY FK_96F4F105CCD7E912');
        $this->addSql('ALTER TABLE menu_rol DROP FOREIGN KEY FK_96F4F105D60322AC');
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107AE6D597C3');
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107A9C9C9E68');
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107ADE734E51');
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107A5688DED7');
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107ACB305D73');
        $this->addSql('ALTER TABLE movimiento DROP FOREIGN KEY FK_C8FF107AFEF0887B');
        $this->addSql('ALTER TABLE movimiento_detalle DROP FOREIGN KEY FK_939E840D58E7D5A2');
        $this->addSql('ALTER TABLE movimiento_detalle DROP FOREIGN KEY FK_939E840D7645698E');
        $this->addSql('ALTER TABLE nota DROP FOREIGN KEY FK_C8D03E0D58E7D5A2');
        $this->addSql('ALTER TABLE nota DROP FOREIGN KEY FK_C8D03E0DDE12AB56');
        $this->addSql('ALTER TABLE nota DROP FOREIGN KEY FK_C8D03E0D16FE72E1');
        $this->addSql('ALTER TABLE product_stock DROP FOREIGN KEY FK_EA6A2D3C4584665A');
        $this->addSql('ALTER TABLE product_stock DROP FOREIGN KEY FK_EA6A2D3C5080ECDE');
        $this->addSql('ALTER TABLE producto DROP FOREIGN KEY FK_A7BB06153397707A');
        $this->addSql('ALTER TABLE producto DROP FOREIGN KEY FK_A7BB06159D01464C');
        $this->addSql('ALTER TABLE producto DROP FOREIGN KEY FK_A7BB06158319D2B3');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D4BAB96C');
        $this->addSql('DROP TABLE almacen');
        $this->addSql('DROP TABLE cash_movement');
        $this->addSql('DROP TABLE cash_movement_category');
        $this->addSql('DROP TABLE cash_register');
        $this->addSql('DROP TABLE cash_shift');
        $this->addSql('DROP TABLE categoria');
        $this->addSql('DROP TABLE cliente');
        $this->addSql('DROP TABLE comprobante');
        $this->addSql('DROP TABLE config');
        $this->addSql('DROP TABLE empresa');
        $this->addSql('DROP TABLE local');
        $this->addSql('DROP TABLE local_comprobante');
        $this->addSql('DROP TABLE media');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_rol');
        $this->addSql('DROP TABLE movimiento');
        $this->addSql('DROP TABLE movimiento_detalle');
        $this->addSql('DROP TABLE nota');
        $this->addSql('DROP TABLE operacion');
        $this->addSql('DROP TABLE payment_method');
        $this->addSql('DROP TABLE price_list');
        $this->addSql('DROP TABLE product_stock');
        $this->addSql('DROP TABLE producto');
        $this->addSql('DROP TABLE proveedor');
        $this->addSql('DROP TABLE rol');
        $this->addSql('DROP TABLE summary');
        $this->addSql('DROP TABLE time_dimension');
        $this->addSql('DROP TABLE tipo_comprobante');
        $this->addSql('DROP TABLE ubigeo');
        $this->addSql('DROP TABLE unidad');
        $this->addSql('DROP TABLE usuario');
    }
}
