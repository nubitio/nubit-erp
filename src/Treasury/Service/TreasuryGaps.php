<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementSource;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/**
 * Payments that should have a Treasury movement and do not: made while the capability was off, or while a POS
 * sale could not reach Treasury (the POS never fails a sale for that, it logs it). Cash is not covered: drawer
 * movements are written with the payment itself. Only the payments with a stable key are found: POS payment
 * lines (their own idempotency key) and supplier payments (their id).
 *
 * Nothing before `$from` is looked at. Balances start at the opening balance an administrator enters (D-D),
 * so recording earlier history would count it twice.
 */
final readonly class TreasuryGaps
{
    private const int CASH_METHOD_ID = 1;

    public function __construct(
        private Connection $connection,
    ) {}

    /** The day Treasury began counting: the earliest opening balance. Null when none has been entered. */
    public function activationDate(): ?DateTimeImmutable
    {
        $day = $this->connection->fetchOne(
            "SELECT MIN(occurred_on) FROM treasury_movement WHERE source_type = 'opening_balance'",
        );

        return is_string($day) && $day !== '' ? new DateTimeImmutable($day) : null;
    }

    /** @return list<MissingMovement> */
    public function missing(DateTimeImmutable $from): array
    {
        return [...$this->missingSalePayments($from), ...$this->missingSupplierPayments($from)];
    }

    /** @return list<MissingMovement> */
    private function missingSalePayments(DateTimeImmutable $from): array
    {
        $collate = $this->collate();
        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT sp.idempotency_key AS source_id, sp.amount, sp.currency, sp.payment_method_id, sp.paid_at, sp.reference,
                       s.branch_id, s.customer_id, s.series, s.number
                FROM sale_payments sp
                JOIN sales s ON s.id = sp.sale_id
                JOIN payment_method pm ON pm.id = sp.payment_method_id
                WHERE sp.payment_method_id <> :cash
                  AND pm.settlement_behavior = 'COLLECTED'
                  AND sp.idempotency_key IS NOT NULL
                  AND s.status <> 'VOIDED'
                  AND sp.paid_at >= :from
                  AND NOT EXISTS (
                      SELECT 1 FROM treasury_movement tm
                      WHERE tm.source_type = 'sale_payment' AND tm.source_id = sp.idempotency_key{$collate} AND tm.leg = 'main'
                  )
                ORDER BY sp.paid_at, sp.id
                SQL,
            ['cash' => self::CASH_METHOD_ID, 'from' => $from->format('Y-m-d 00:00:00')],
        );

        return array_map(
            static fn(array $row): MissingMovement => new MissingMovement(
                source: MovementSource::SalePayment,
                sourceId: (string) $row['source_id'],
                direction: Direction::In,
                amount: (string) $row['amount'],
                currency: (string) $row['currency'],
                branchId: (string) $row['branch_id'],
                paymentMethodId: (int) $row['payment_method_id'],
                occurredAt: new DateTimeImmutable((string) $row['paid_at']),
                partyType: $row['customer_id'] === null ? null : 'customer',
                partyId: $row['customer_id'] === null ? null : (string) $row['customer_id'],
                reference: $row['reference'] === null ? null : (string) $row['reference'],
                description: self::label('Venta', $row['series'] ?? null, $row['number'] ?? null),
            ),
            $rows,
        );
    }

    /** @return list<MissingMovement> */
    private function missingSupplierPayments(DateTimeImmutable $from): array
    {
        $collate = $this->collate();
        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT sp.id AS source_id, sp.amount, b.currency, sp.payment_method_id, sp.paid_at, sp.reference,
                       b.branch_id, b.supplier_id, b.series, b.number
                FROM supplier_payment sp
                JOIN supplier_bill b ON b.id = sp.supplier_bill_id
                WHERE sp.payment_method_id <> :cash
                  AND sp.reversed_at IS NULL
                  AND sp.paid_at >= :from
                  AND NOT EXISTS (
                      SELECT 1 FROM treasury_movement tm
                      WHERE tm.source_type = 'supplier_payment' AND tm.source_id = CAST(sp.id AS CHAR){$collate} AND tm.leg = 'main'
                  )
                ORDER BY sp.paid_at, sp.id
                SQL,
            ['cash' => self::CASH_METHOD_ID, 'from' => $from->format('Y-m-d')],
        );

        return array_map(
            static fn(array $row): MissingMovement => new MissingMovement(
                source: MovementSource::SupplierPayment,
                sourceId: (string) $row['source_id'],
                direction: Direction::Out,
                amount: (string) $row['amount'],
                currency: (string) $row['currency'],
                branchId: (string) $row['branch_id'],
                paymentMethodId: (int) $row['payment_method_id'],
                occurredAt: new DateTimeImmutable((string) $row['paid_at']),
                partyType: 'supplier',
                partyId: (string) $row['supplier_id'],
                reference: $row['reference'] === null ? null : (string) $row['reference'],
                description: self::label('Pago factura', $row['series'] ?? null, $row['number'] ?? null),
            ),
            $rows,
        );
    }

    /**
     * The Treasury tables are created with utf8mb4_unicode_ci while older tables use the server default; MariaDB
     * refuses to compare the two without saying which wins. SQLite has no such collation, so only MySQL gets it.
     */
    private function collate(): string
    {
        return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform
            ? ' COLLATE utf8mb4_unicode_ci'
            : '';
    }

    private static function label(string $prefix, mixed $series, mixed $number): string
    {
        $series = is_string($series) ? $series : '';
        $number = is_string($number) ? $number : '';

        return $series !== '' && $number !== '' ? sprintf('%s %s-%s', $prefix, $series, $number) : $prefix;
    }
}
