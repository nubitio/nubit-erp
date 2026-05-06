<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds descuento_porcentaje DECIMAL(5,2) NULL to sale_items.
 *
 * The existing descuento DECIMAL(10,2) column stores an absolute monetary
 * discount amount. This new column stores the discount as a percentage
 * (0.00–100.00) so that percentage-based discounts can be recorded and
 * later used to recalculate the absolute amount when prices change.
 */
final class Version20260506000001_sale_item_descuento_porcentaje extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add descuento_porcentaje DECIMAL(5,2) NULL to sale_items';
    }

    public function up(Schema $schema): void
    {
        // Use IF NOT EXISTS guard; omit AFTER descuento since the column may not
        // exist in environments that were set up from the newer normalized schema.
        $this->addSql('ALTER TABLE sale_items ADD COLUMN IF NOT EXISTS descuento_porcentaje DECIMAL(5,2) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_items DROP COLUMN descuento_porcentaje');
    }
}
