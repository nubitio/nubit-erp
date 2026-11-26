<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashSessionClosureSummaryDto;
use App\CashRegister\Service\SessionSalesBreakdown;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Identity\Auth\BranchContextServiceInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<CashSessionClosureSummaryDto>
 */
readonly class CashSessionClosureSummaryProvider implements ProviderInterface
{
    /** ID of the cash payment method row; mirrors PaymentMethod::CASH from Catalog. */
    private const int CASH_PAYMENT_METHOD_ID = 1;

    public function __construct(
        private Connection $connection,
        private SessionSalesBreakdown $breakdown,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     * @return CashSessionClosureSummaryDto[]
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $cashSessionId = $uriVariables['id'];

        $session = $this->connection->fetchAssociative('SELECT id, opening_amount FROM cash_sessions WHERE id = ? AND branch_id = ?', [
            $cashSessionId,
            $this->branchContext->getCurrentBranchId(),
        ]);

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %s.', $cashSessionId));
        }

        $sessionId = (int) $session['id'];
        $openingAmount = BigDecimal::of((string) $session['opening_amount']);

        $cashInRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_IN'",
            [$sessionId],
        );
        $cashInTotal = BigDecimal::of((string) ($cashInRaw ?: '0'));

        $cashOutRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_OUT'",
            [$sessionId],
        );
        $cashOutTotal = BigDecimal::of((string) ($cashOutRaw ?: '0'));

        $v2Sales = $this->connection->fetchAllAssociative("SELECT cm.payment_method_id,
                COALESCE(SUM(CASE WHEN cm.movement_type = 'REFUND_OUT' THEN -cm.amount ELSE cm.amount END), 0) AS total
             FROM cash_movements cm
             WHERE cm.cash_session_id = ? AND cm.movement_type IN ('SALE_IN', 'REFUND_OUT')
             GROUP BY cm.payment_method_id", [$sessionId]);

        /** @var array<int, BigDecimal> $salesMap */
        $salesMap = [];
        foreach ($v2Sales as $row) {
            $pmId = (int) $row['payment_method_id'];
            $salesMap[$pmId] = BigDecimal::of((string) $row['total']);
        }

        $v2CashSales = $salesMap[self::CASH_PAYMENT_METHOD_ID] ?? BigDecimal::zero();
        $cashExpected = $openingAmount
            ->plus($cashInTotal)
            ->minus($cashOutTotal)
            ->plus($v2CashSales)
            ->toScale(2, RoundingMode::HALF_UP)
            ->__toString();

        $cashName = $this->connection->fetchOne('SELECT name FROM payment_method WHERE id = ?', [
            self::CASH_PAYMENT_METHOD_ID,
        ]) ?: 'Efectivo';

        $dtos = [
            new CashSessionClosureSummaryDto(
                paymentMethodId: self::CASH_PAYMENT_METHOD_ID,
                paymentMethod: (string) $cashName,
                expectedAmount: $cashExpected,
            ),
        ];

        // The other methods never touch the drawer, so their expected amount is what the shift sold with them
        // (to be checked against the terminal, the app or the bank), not what the drawer holds.
        foreach ($this->breakdown->forSession($sessionId)['methods'] as $method) {
            if ($method['id'] === self::CASH_PAYMENT_METHOD_ID) {
                continue;
            }

            $dtos[] = new CashSessionClosureSummaryDto(
                paymentMethodId: $method['id'],
                paymentMethod: $method['name'],
                expectedAmount: $method['amount'],
            );
        }

        return $dtos;
    }
}
