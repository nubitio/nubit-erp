<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a multiplier column to price_list.
 *
 * When set, product prices in a price list are derived as:
 *   price_list_detail.price  (explicit override, highest priority)
 *   OR base_price × multiplier (fallback when no row in price_list_detail)
 *   OR base_price             (when multiplier is also NULL — original behaviour)
 *
 * NULL means "no multiplier" (pure per-product overrides only).
 * 1.00 means "same price as base" — useful as a starting point.
 */
final class Version20260619000002_price_list_multiplier extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multiplier DECIMAL(8,4) to price_list';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE price_list
             ADD COLUMN multiplier DECIMAL(8,4) DEFAULT NULL
             COMMENT "Optional global multiplier: effective_price = base_price × multiplier when no explicit override exists"
             AFTER name'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE price_list DROP COLUMN multiplier');
    }
}
