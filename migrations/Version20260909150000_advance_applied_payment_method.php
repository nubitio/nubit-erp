<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #382: an internal `payment_method` for a customer advance applied to a sale.
 * `settlement_behavior = 'ADVANCE'` — it produces a `sale_payments` row but no
 * cash movement, and the POS never offers it.
 */
final class Version20260909150000_advance_applied_payment_method extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Seed payment_method 'Anticipo aplicado' (settlement_behavior ADVANCE) (#382)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO payment_method (name, settlement_behavior, is_active)
             SELECT 'Anticipo aplicado', 'ADVANCE', 1
             FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM payment_method WHERE name = 'Anticipo aplicado')",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM payment_method WHERE name = 'Anticipo aplicado' AND settlement_behavior = 'ADVANCE'");
    }
}
