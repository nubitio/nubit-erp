<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashShiftXReportDto;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // Step 1: try direct v2 session ID
        $session = $this->connection->fetchAssociative(
            'SELECT id, opening_amount, opened_at FROM cash_sessions WHERE id = ?',
            [$cashShiftId]
        );

        // Step 2: fallback — treat URI id as a legacy_cash_shift_id
        if ($session === false) {
            $session = $this->connection->fetchAssociative(
                'SELECT id, opening_amount, opened_at FROM cash_sessions WHERE legacy_cash_shift_id = ?',
                [$cashShiftId]
            );
        }

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %s.', $cashShiftId));
        }

        $sessionId      = (int) $session['id'];
        $openingBalance = BigDecimal::of((string) $session['opening_amount']);

        // Total SALE_IN
        $totalSalesRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'SALE_IN'",
            [$sessionId]
        );
        $totalSales = BigDecimal::of((string) ($totalSalesRaw ?: '0'));

        // CASH_IN total
        $cashInRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_IN'",
            [$sessionId]
        );
        $cashIn = BigDecimal::of((string) ($cashInRaw ?: '0'));

        // CASH_OUT total
        $cashOutRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_OUT'",
            [$sessionId]
        );
        $cashOut = BigDecimal::of((string) ($cashOutRaw ?: '0'));

        // Sales by payment method
        $salesRows = $this->connection->fetchAllAssociative(
            "SELECT pm.id AS paymentMethodId, pm.name AS paymentMethod, COALESCE(SUM(cm.amount), 0) AS amount
             FROM cash_movements cm
             JOIN payment_method pm ON pm.id = cm.payment_method_id
             WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
             GROUP BY cm.payment_method_id, pm.name",
            [$sessionId]
        );

        // Shift duration in minutes from v2 cash_sessions
        $shiftDurationMinutes = (int) ($this->connection->fetchOne(
            'SELECT TIMESTAMPDIFF(MINUTE, opened_at, NOW()) FROM cash_sessions WHERE id = ?',
            [$sessionId]
        ) ?: 0);

        $currentBalance = $openingBalance->plus($totalSales)->plus($cashIn)->minus($cashOut);

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
