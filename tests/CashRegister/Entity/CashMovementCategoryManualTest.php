<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\Entity;

use App\CashRegister\Entity\CashMovementCategory;
use PHPUnit\Framework\TestCase;

final class CashMovementCategoryManualTest extends TestCase
{
    public function testACategoryIsNotManualUnlessMarked(): void
    {
        static::assertFalse(new CashMovementCategory()->isManual());
        static::assertTrue(
            new CashMovementCategory()
                ->setManual(true)
                ->isManual(),
        );
    }

    public function testTheManualReasonsAreTheOnesAPersonDecides(): void
    {
        static::assertEqualsCanonicalizing(
            ['cash_replenishment', 'other_income', 'petty_cash_expense', 'supplier_payment', 'manager_withdrawal'],
            CashMovementCategory::MANUAL_CODES,
        );
        // What the system records itself must never be offered: it would count the same money twice.
        foreach ([
            CashMovementCategory::CASH_SALE,
            CashMovementCategory::CREDIT_PAYMENT,
            CashMovementCategory::CUSTOMER_REFUND,
            CashMovementCategory::OVERAGE_ADJUSTMENT,
            CashMovementCategory::SHORTAGE_ADJUSTMENT,
            CashMovementCategory::BANK_DEPOSIT,
        ] as $code) {
            static::assertNotContains($code, CashMovementCategory::MANUAL_CODES);
        }
    }

    /** The migration marks existing tenants; the seeders mark new ones. They must agree. */
    public function testTheMigrationMarksTheSameReasonsAsTheSeeders(): void
    {
        $source = (string) file_get_contents(__DIR__
        . '/../../../migrations/Version20261123120000_cash_movement_category_manual.php');
        preg_match('/MANUAL_CODES = \[(.*?)\];/s', $source, $block);
        preg_match_all("/'([a-z_]+)'/", $block[1] ?? '', $codes);

        static::assertEqualsCanonicalizing(CashMovementCategory::MANUAL_CODES, $codes[1]);
    }
}
