<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\CashRegister\CashShiftClosureSummaryDto;
use App\Entity\PaymentMethod;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * @implements ProviderInterface<CashShiftClosureSummaryDto>
 */
readonly class CashShiftClosureSummaryProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritdoc
     * @throws Exception
     * @return CashShiftClosureSummaryDto[]
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $cashShiftId = $uriVariables['id'];

        // Fetch V2 cash_session for this legacy shift
        $session = $this->connection->fetchAssociative(
            'SELECT id, opening_amount FROM cash_sessions WHERE legacy_cash_shift_id = ?',
            [$cashShiftId]
        );

        // Legacy movements: income and expense sums
        $legacyIncome = BigDecimal::of(
            (string) ($this->connection->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_movement WHERE cash_shift_id = ? AND type = 'income'",
                [$cashShiftId]
            ) ?: '0')
        );
        $legacyExpense = BigDecimal::of(
            (string) ($this->connection->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) FROM cash_movement WHERE cash_shift_id = ? AND type = 'expense'",
                [$cashShiftId]
            ) ?: '0')
        );

        if ($session === false) {
            // Graceful fallback: legacy data only, single cash row
            $cashExpected = $legacyIncome->minus($legacyExpense)
                ->toScale(2, RoundingMode::HALF_UP)
                ->__toString();

            $cashName = $this->connection->fetchOne(
                'SELECT name FROM payment_method WHERE id = ?',
                [PaymentMethod::CASH]
            ) ?: 'Efectivo';

            return [
                new CashShiftClosureSummaryDto(
                    paymentMethodId: PaymentMethod::CASH,
                    paymentMethod: (string) $cashName,
                    expectedAmount: $cashExpected,
                ),
            ];
        }

        $sessionId = (int) $session['id'];
        $openingAmount = BigDecimal::of((string) $session['opening_amount']);

        // V2 SALE_IN amounts grouped by payment_method_id
        $v2Sales = $this->connection->fetchAllAssociative(
            "SELECT cm.payment_method_id, COALESCE(SUM(cm.amount), 0) AS total
             FROM cash_movements cm
             WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
             GROUP BY cm.payment_method_id",
            [$sessionId]
        );

        // Build a map: payment_method_id => BigDecimal total
        $salesMap = [];
        foreach ($v2Sales as $row) {
            $pmId = (int) $row['payment_method_id'];
            $salesMap[$pmId] = BigDecimal::of((string) $row['total']);
        }

        // Cash row: opening + legacy income - legacy expense + V2 SALE_IN (cash only)
        $v2CashSales = $salesMap[PaymentMethod::CASH] ?? BigDecimal::zero();
        $cashExpected = $openingAmount
            ->plus($legacyIncome)
            ->minus($legacyExpense)
            ->plus($v2CashSales)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();

        $cashName = $this->connection->fetchOne(
            'SELECT name FROM payment_method WHERE id = ?',
            [PaymentMethod::CASH]
        ) ?: 'Efectivo';

        $dtos = [
            new CashShiftClosureSummaryDto(
                paymentMethodId: PaymentMethod::CASH,
                paymentMethod: (string) $cashName,
                expectedAmount: $cashExpected,
            ),
        ];

        // Non-cash rows: one per non-cash payment method that has at least one SALE_IN
        foreach ($salesMap as $pmId => $total) {
            if ($pmId === PaymentMethod::CASH) {
                continue;
            }

            $pmName = $this->connection->fetchOne(
                'SELECT name FROM payment_method WHERE id = ?',
                [$pmId]
            ) ?: 'Desconocido';

            $dtos[] = new CashShiftClosureSummaryDto(
                paymentMethodId: $pmId,
                paymentMethod: (string) $pmName,
                expectedAmount: $total->toScale(2, RoundingMode::HALF_UP)->__toString(),
            );
        }

        return $dtos;
    }
}
