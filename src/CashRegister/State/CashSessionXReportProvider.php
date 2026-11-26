<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashSessionPaymentMethodSalesDto;
use App\CashRegister\Dto\CashSessionXReportDto;
use App\CashRegister\Service\SessionSalesBreakdown;
use App\Identity\Auth\BranchContextServiceInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Identity\Auth\BranchContextServiceInterface;
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
        private SessionSalesBreakdown $breakdown,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @inheritdoc
     * @throws Exception
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CashSessionXReportDto
    {
        $cashSessionId = $uriVariables['id'];

        $session = $this->connection->fetchAssociative('SELECT id, opening_amount, opened_at FROM cash_sessions WHERE id = ? AND branch_id = ?', [
            $cashSessionId,
            $this->branchContext->getCurrentBranchId(),
        ]);

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %s.', $cashSessionId));
        }

        $sessionId = (int) $session['id'];
        $openingBalance = BigDecimal::of((string) $session['opening_amount']);

        // What went into the drawer from sales. The balance of the drawer is built from this, not from what the
        // shift sold: only cash moves it.
        $cashSalesRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'SALE_IN'",
            [$sessionId],
        );
        $cashSalesIn = BigDecimal::of((string) ($cashSalesRaw ?: '0'));
        $sold = $this->breakdown->forSession($sessionId);
        $totalSales = BigDecimal::of($sold['total']);

        $cashInRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'CASH_IN'",
            [$sessionId],
        );
        $cashIn = BigDecimal::of((string) ($cashInRaw ?: '0'));

        $cashOutRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type IN ('CASH_OUT', 'REFUND_OUT')",
            [$sessionId],
        );
        $cashOut = BigDecimal::of((string) ($cashOutRaw ?: '0'));

        $shiftDurationMinutes = (int) (
            $this->connection->fetchOne('SELECT TIMESTAMPDIFF(MINUTE, opened_at, NOW()) FROM cash_sessions WHERE id = ?', [
                $sessionId,
            ]) ?: 0
        );

        // Cash-only reconciliation mirrors the closure summary. Digital refunds
        // belong in the overall report, but must not reduce physical cash.
        $cashBreakdown = $this->connection->fetchAssociative(
            "SELECT
                COALESCE(SUM(CASE
                    WHEN payment_method_id = 1 AND movement_type = 'SALE_IN' THEN amount
                    WHEN payment_method_id = 1 AND movement_type = 'REFUND_OUT' THEN -amount
                    ELSE 0 END), 0) AS cash_sales_net,
                COALESCE(SUM(CASE WHEN movement_type = 'CASH_OUT' THEN amount ELSE 0 END), 0) AS cash_adjustments_out
             FROM cash_movements WHERE cash_session_id = ?",
            [$sessionId],
        );

        $withdrawalsRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(cm.amount), 0) FROM cash_movements cm
             JOIN cash_movement_category cmc ON cmc.id = cm.category_id
             WHERE cm.cash_session_id = ? AND cm.movement_type = 'CASH_OUT'
               AND cmc.code IN ('manager_withdrawal', 'bank_deposit')",
            [$sessionId],
        );

        // Receipts of the shift's sales that count: voided sales are left out, as in the rest of the report.
        $documentRows = $this->connection->fetchAllAssociative("SELECT td.document_type, COUNT(*) AS total
             FROM tax_document td
             JOIN sales s ON s.id = td.sale_id
             WHERE s.cash_session_id = ? AND s.deleted_at IS NULL AND s.status <> 'VOIDED'
             GROUP BY td.document_type", [$sessionId]);
        $documents = ['boletas' => 0, 'facturas' => 0, 'creditNotes' => 0];
        foreach ($documentRows as $documentRow) {
            match ((string) $documentRow['document_type']) {
                '03' => $documents['boletas'] = (int) $documentRow['total'],
                '01' => $documents['facturas'] = (int) $documentRow['total'],
                '07' => $documents['creditNotes'] = (int) $documentRow['total'],
                default => null,
            };
        }

        $currentBalance = $openingBalance->plus($cashSalesIn)->plus($cashIn)->minus($cashOut);

        $salesByPaymentMethod = array_map(
            static fn(array $method): CashSessionPaymentMethodSalesDto => new CashSessionPaymentMethodSalesDto(
                paymentMethodId: $method['id'],
                paymentMethod: $method['name'],
                amount: $method['amount'],
                operations: $method['operations'],
            ),
            $sold['methods'],
        );

        return new CashSessionXReportDto(
            openingBalance: $openingBalance->toScale(2, RoundingMode::HALF_UP)->__toString(),
            totalSales: $totalSales->toScale(2, RoundingMode::HALF_UP)->__toString(),
            cashIn: $cashIn->toScale(2, RoundingMode::HALF_UP)->__toString(),
            cashOut: $cashOut->toScale(2, RoundingMode::HALF_UP)->__toString(),
            currentBalance: $currentBalance->toScale(2, RoundingMode::HALF_UP)->__toString(),
            shiftDurationMinutes: $shiftDurationMinutes,
            salesByPaymentMethod: $salesByPaymentMethod,
            cashSalesNet: BigDecimal::of((string) ($cashBreakdown['cash_sales_net'] ?? '0'))->toScale(
                2,
                RoundingMode::HALF_UP,
            )->__toString(),
            cashAdjustmentsOut: BigDecimal::of((string) ($cashBreakdown['cash_adjustments_out'] ?? '0'))->toScale(
                2,
                RoundingMode::HALF_UP,
            )->__toString(),
            receivableTotal: $sold['receivable']['amount'],
            receivableOperations: $sold['receivable']['operations'],
            withdrawalsOut: BigDecimal::of((string) ($withdrawalsRaw ?: '0'))->toScale(
                2,
                RoundingMode::HALF_UP,
            )->__toString(),
            documents: $documents,
        );
    }
}
