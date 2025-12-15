<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250212000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow fractional stock: change stock columns to decimal(12,3) for products and product_stock';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product_stock MODIFY stock DECIMAL(12,3) NOT NULL");
        $this->addSql("ALTER TABLE product_stock MODIFY min_stock DECIMAL(12,3) NOT NULL");
        $this->addSql("ALTER TABLE producto MODIFY stock DECIMAL(12,3) DEFAULT NULL");
        $this->addSql("ALTER TABLE producto MODIFY stock_minimo DECIMAL(12,3) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product_stock MODIFY stock INT NOT NULL");
        $this->addSql("ALTER TABLE product_stock MODIFY min_stock INT NOT NULL");
        $this->addSql("ALTER TABLE producto MODIFY stock INT DEFAULT NULL");
        $this->addSql("ALTER TABLE producto MODIFY stock_minimo INT DEFAULT NULL");
    }
}
