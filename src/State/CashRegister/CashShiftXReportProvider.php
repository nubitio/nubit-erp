<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\CashRegister\CashShiftXReportDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * @implements ProviderInterface<CashShiftXReportDto>
 */
readonly class CashShiftXReportProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CashShiftXReportDto
    {
        $cashShiftId = $uriVariables['id'];

        // Opening balance: prefer V2 cash_sessions, fallback to legacy cash_shift
        $session = $this->connection->fetchAssociative(
            'SELECT id, opening_amount FROM cash_sessions WHERE legacy_cash_shift_id = ?',
            [$cashShiftId]
        );

        if ($session !== false) {
            $openingBalance = BigDecimal::of((string) $session['opening_amount']);
            $sessionId = (int) $session['id'];

            // Total SALE_IN from V2
            $totalSalesRaw = $this->connection->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
                 WHERE cash_session_id = ? AND movement_type = 'SALE_IN'",
                [$sessionId]
            );
            $totalSales = BigDecimal::of((string) ($totalSalesRaw ?: '0'));

            // Sales by payment method from V2
            $salesRows = $this->connection->fetchAllAssociative(
                "SELECT pm.id AS paymentMethodId, pm.name AS paymentMethod, COALESCE(SUM(cm.amount), 0) AS amount
                 FROM cash_movements cm
                 JOIN payment_method pm ON pm.id = cm.payment_method_id
                 WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
                 GROUP BY cm.payment_method_id, pm.name",
                [$sessionId]
            );
        } else {
            // Fallback: use legacy cash_shift opening_balance
            $legacyOpeningRaw = $this->connection->fetchOne(
                'SELECT opening_balance FROM cash_shift WHERE id = ?',
                [$cashShiftId]
            );
            $openingBalance = BigDecimal::of((string) ($legacyOpeningRaw ?: '0'));
            $totalSales = BigDecimal::zero();
            $salesRows = [];
        }

        // Legacy income
        $legacyIncomeRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movement WHERE cash_shift_id = ? AND type = 'income'",
            [$cashShiftId]
        );
        $cashIn = BigDecimal::of((string) ($legacyIncomeRaw ?: '0'));

        // Legacy expense
        $legacyExpenseRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movement WHERE cash_shift_id = ? AND type = 'expense'",
            [$cashShiftId]
        );
        $cashOut = BigDecimal::of((string) ($legacyExpenseRaw ?: '0'));

        // Current balance
        $currentBalance = $openingBalance->plus($totalSales)->plus($cashIn)->minus($cashOut);

        // Shift duration in minutes
        $shiftDurationMinutes = (int) ($this->connection->fetchOne(
            'SELECT TIMESTAMPDIFF(MINUTE, opened_at, NOW()) FROM cash_shift WHERE id = ?',
            [$cashShiftId]
        ) ?: 0);

        // Build sales by payment method array
        $salesByPaymentMethod = array_map(
            static fn (array $row): array => [
                'paymentMethodId' => (int) $row['paymentMethodId'],
                'paymentMethod'   => (string) $row['paymentMethod'],
                'amount'          => BigDecimal::of((string) $row['amount'])
                    ->toScale(2, RoundingMode::HALF_UP)
                    ->__toString(),
            ],
            $salesRows
        );

        return new CashShiftXReportDto(
            openingBalance: $openingBalance->toScale(2, RoundingMode::HALF_UP)->__toString(),
            totalSales: $totalSales->toScale(2, RoundingMode::HALF_UP)->__toString(),
            cashIn: $cashIn->toScale(2, RoundingMode::HALF_UP)->__toString(),
            cashOut: $cashOut->toScale(2, RoundingMode::HALF_UP)->__toString(),
            currentBalance: $currentBalance->toScale(2, RoundingMode::HALF_UP)->__toString(),
            shiftDurationMinutes: $shiftDurationMinutes,
            salesByPaymentMethod: $salesByPaymentMethod,
        );
    }
}
