<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\Sales\Entity\SaleRecord;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

/** What a sale leaves behind once it has an id: its payment, the cash that went into the drawer, and any debt. */
final class SalePostings
{
    /** @var array<int|string, string> running receivable balance per customer id */
    private array $receivable = [];

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function post(SaleRecord $sale, SalePlan $plan, int $sessionId, SeedContext $context): void
    {
        $userId = (int) $context->cashiers[$plan->cashier]->getId();

        if (!$plan->isCredit()) {
            $paidAt = Clock::stamp($plan->at);
            $this->connection->executeStatement('INSERT INTO sale_payments (sale_id, payment_method_id, amount, currency, paid_at, created_at, sync_status, version_no, last_modified_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)', [
                (string) $sale->getId(),
                $context->paymentMethods[$plan->payment]->getId(),
                $sale->getGrandTotal(),
                'PEN',
                $paidAt,
                $paidAt,
                'SYNCED',
                $paidAt,
            ]);
        }

        if ($plan->keepsCash()) {
            $this->movement(
                $sessionId,
                'SALE_IN',
                'cash_sale',
                $sale->getGrandTotal(),
                'Venta ' . $sale->getInvoiceFullNumber(),
                $plan->at,
                $userId,
                (string) $sale->getId(),
                paymentMethodId: $context->paymentMethods['Efectivo']->getId(),
            );
        }

        if ($plan->isCredit() && $plan->outcome === SalePlan::NORMAL) {
            $this->charge($sale, $plan, $context);
        }
    }

    public function movement(
        int $sessionId,
        string $type,
        ?string $category,
        string $amount,
        string $description,
        DateTimeImmutable $at,
        int $userId,
        ?string $saleId = null,
        ?int $paymentMethodId = null,
    ): void {
        $categoryId = $category !== null
            ? $this->connection->fetchOne('SELECT id FROM cash_movement_category WHERE code = ?', [$category])
            : null;
        $stamp = Clock::stamp($at);

        $this->connection->executeStatement('INSERT INTO cash_movements (cash_session_id, movement_type, category_id, sale_id, payment_method_id, amount, description, occurred_at, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $sessionId,
            $type,
            $categoryId === false ? null : $categoryId,
            $saleId,
            $paymentMethodId,
            $amount,
            $description,
            $stamp,
            $userId,
            $stamp,
        ]);
    }

    private function charge(SaleRecord $sale, SalePlan $plan, SeedContext $context): void
    {
        $customer = $context->customers[(string) $plan->customerKey];
        $customerId = (string) $customer->getId();
        $balance = BigDecimal::of($this->receivable[$customerId] ?? '0.00')
            ->plus($sale->getGrandTotal())
            ->toScale(2)
            ->__toString();
        $this->receivable[$customerId] = $balance;

        $this->connection->executeStatement(
            'INSERT INTO customer_account_entries
                (customer_id, entry_type, occurred_on, debit, credit, currency, running_balance, sale_id, source, source_id, description, created_at)
             VALUES (?, \'charge\', ?, ?, 0, \'PEN\', ?, ?, \'sale\', ?, ?, ?)',
            [
                $customer->getId(),
                $plan->at->format('Y-m-d'),
                $sale->getGrandTotal(),
                $balance,
                (string) $sale->getId(),
                (string) $sale->getId(),
                'Venta a crédito ' . $sale->getInvoiceFullNumber(),
                Clock::stamp($plan->at),
            ],
        );
    }
}
