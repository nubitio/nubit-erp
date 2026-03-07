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
        $sessionId = (int) $uriVariables['id'];

        // Opening balance from cash_sessions v2
        $openingAmountRaw = $this->connection->fetchOne(
            'SELECT opening_amount FROM cash_sessions WHERE id = ?',
            [$sessionId]
        );
        $openingBalance = BigDecimal::of((string) ($openingAmountRaw ?: '0'));

        // Total SALE_IN from cash_movements v2
        $totalSalesRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'SALE_IN'",
            [$sessionId]
        );
        $totalSales = BigDecimal::of((string) ($totalSalesRaw ?: '0'));

        // Total CASH_IN from cash_movements v2
        $cashInRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_IN'",
            [$sessionId]
        );
        $cashIn = BigDecimal::of((string) ($cashInRaw ?: '0'));

        // Total CASH_OUT from cash_movements v2
        $cashOutRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_OUT'",
            [$sessionId]
        );
        $cashOut = BigDecimal::of((string) ($cashOutRaw ?: '0'));

        // Sales by payment method from cash_movements v2
        $salesRows = $this->connection->fetchAllAssociative(
            "SELECT pm.id AS paymentMethodId, pm.name AS paymentMethod, COALESCE(SUM(cm.amount), 0) AS amount
             FROM cash_movements cm
             JOIN payment_method pm ON pm.id = cm.payment_method_id
             WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
             GROUP BY cm.payment_method_id, pm.name",
            [$sessionId]
        );

        // Current balance = opening + SALE_IN + CASH_IN - CASH_OUT
        $currentBalance = $openingBalance->plus($totalSales)->plus($cashIn)->minus($cashOut);

        // Shift duration in minutes from cash_sessions.opened_at
        $shiftDurationMinutes = (int) ($this->connection->fetchOne(
            'SELECT TIMESTAMPDIFF(MINUTE, opened_at, NOW()) FROM cash_sessions WHERE id = ?',
            [$sessionId]
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
