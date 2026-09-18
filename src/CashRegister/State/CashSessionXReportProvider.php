<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashSessionPaymentMethodSalesDto;
use App\CashRegister\Dto\CashSessionXReportDto;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<CashSessionXReportDto>
 */
readonly class CashSessionXReportProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CashSessionXReportDto
    {
        $cashSessionId = $uriVariables['id'];

        $session = $this->connection->fetchAssociative('SELECT id, opening_amount, opened_at FROM cash_sessions WHERE id = ?', [
            $cashSessionId,
        ]);

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %s.', $cashSessionId));
        }

        $sessionId = (int) $session['id'];
        $openingBalance = BigDecimal::of((string) $session['opening_amount']);

        $totalSalesRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'SALE_IN'",
            [$sessionId],
        );
        $totalSales = BigDecimal::of((string) ($totalSalesRaw ?: '0'));

        $cashInRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_IN'",
            [$sessionId],
        );
        $cashIn = BigDecimal::of((string) ($cashInRaw ?: '0'));

        $cashOutRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_OUT'",
            [$sessionId],
        );
        $cashOut = BigDecimal::of((string) ($cashOutRaw ?: '0'));

        $salesRows = $this->connection->fetchAllAssociative(
            "SELECT pm.id AS paymentMethodId, pm.name AS paymentMethod, COALESCE(SUM(cm.amount), 0) AS amount
             FROM cash_movements cm
             JOIN payment_method pm ON pm.id = cm.payment_method_id
             WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
             GROUP BY cm.payment_method_id, pm.name",
            [$sessionId],
        );

        $shiftDurationMinutes = (int) (
            $this->connection->fetchOne('SELECT TIMESTAMPDIFF(MINUTE, opened_at, NOW()) FROM cash_sessions WHERE id = ?', [
                $sessionId,
            ]) ?: 0
        );

        $currentBalance = $openingBalance->plus($totalSales)->plus($cashIn)->minus($cashOut);

        $salesByPaymentMethod = array_map(
            static fn(array $row): CashSessionPaymentMethodSalesDto => new CashSessionPaymentMethodSalesDto(
                paymentMethodId: (int) $row['paymentMethodId'],
                paymentMethod: (string) $row['paymentMethod'],
                amount: BigDecimal::of((string) $row['amount'])->toScale(2, RoundingMode::HALF_UP)->__toString(),
            ),
            $salesRows,
        );

        return new CashSessionXReportDto(
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
