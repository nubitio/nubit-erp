<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * When each product last changed, kept by the database itself so that it also covers
 * imports and any write that does not go through the ORM. Lets the till ask for what
 * changed since its last visit instead of downloading the whole catalogue.
 */
final class Version20261122000000_product_updated_at extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add updated_at (maintained by the database) to producto, indexed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE producto
            ADD updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ADD INDEX IDX_producto_updated_at (updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE producto DROP INDEX IDX_producto_updated_at, DROP updated_at');
    }
}
