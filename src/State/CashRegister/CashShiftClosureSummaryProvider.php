<?php

declare(strict_types=1);

namespace App\State\CashRegister;

use Override;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\CashRegister\CashShiftClosureSummaryDto;
use App\Catalog\Entity\PaymentMethod;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

        // Step 1: try direct v2 session ID
        $session = $this->connection->fetchAssociative(
            'SELECT id, opening_amount FROM cash_sessions WHERE id = ?',
            [$cashShiftId]
        );

        // Step 2: fallback — treat URI id as a legacy_cash_shift_id
        if ($session === false) {
            $session = $this->connection->fetchAssociative(
                'SELECT id, opening_amount FROM cash_sessions WHERE legacy_cash_shift_id = ?',
                [$cashShiftId]
            );
        }

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %s.', $cashShiftId));
        }

        $sessionId     = (int) $session['id'];
        $openingAmount = BigDecimal::of((string) $session['opening_amount']);

        // CASH_IN total from v2
        $cashInRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_IN'",
            [$sessionId]
        );
        $cashInTotal = BigDecimal::of((string) ($cashInRaw ?: '0'));

        // CASH_OUT total from v2
        $cashOutRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_OUT'",
            [$sessionId]
        );
        $cashOutTotal = BigDecimal::of((string) ($cashOutRaw ?: '0'));

        // SALE_IN amounts grouped by payment_method_id from v2
        $v2Sales = $this->connection->fetchAllAssociative(
            "SELECT cm.payment_method_id, COALESCE(SUM(cm.amount), 0) AS total
             FROM cash_movements cm
             WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
             GROUP BY cm.payment_method_id",
            [$sessionId]
        );

        // Build a map: payment_method_id => BigDecimal total
        /** @var array<int, BigDecimal> $salesMap */
        $salesMap = [];
        foreach ($v2Sales as $row) {
            $pmId             = (int) $row['payment_method_id'];
            $salesMap[$pmId]  = BigDecimal::of((string) $row['total']);
        }

        // Cash row: opening_amount + CASH_IN - CASH_OUT + SALE_IN(cash)
        $v2CashSales  = $salesMap[PaymentMethod::CASH] ?? BigDecimal::zero();
        $cashExpected = $openingAmount
            ->plus($cashInTotal)
            ->minus($cashOutTotal)
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

        // Non-cash rows: one per non-cash payment method with SALE_IN movements
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
