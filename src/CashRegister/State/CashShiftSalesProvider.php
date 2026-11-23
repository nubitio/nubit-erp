<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashShiftSalesDto;
use App\CashRegister\Service\OpenCashSessionLocator;
use App\CashRegister\Service\SessionSalesBreakdown;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Security\Security;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Override;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @implements ProviderInterface<CashShiftSalesDto>
 */
readonly class CashShiftSalesProvider implements ProviderInterface
{
    private const string VOIDED = 'VOIDED';

    public function __construct(
        private Connection $connection,
        private Security $security,
        private BranchContextServiceInterface $branchContext,
        private OpenCashSessionLocator $sessions,
        private SessionSalesBreakdown $breakdown,
    ) {}

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CashShiftSalesDto
    {
        $user = $this->security->connectedUser();
        if ($user === null) {
            throw new UnauthorizedHttpException('Bearer', 'Usuario no autenticado.');
        }

        $session = $this->sessions->locate($user->userId, $this->branchContext->getCurrentBranchId());
        if ($session === null) {
            return new CashShiftSalesDto(null, 0, '0.00', [], [], ['amount' => '0.00', 'operations' => 0]);
        }
        $sessionId = (int) $session['id'];

        // The document is the latest one issued for the sale (a rejected one is replaced by another), and
        // credit notes (reason_code) are not the sale's own receipt.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT s.id, s.issued_at, s.status, s.grand_total, s.receivable_account,
                    td.document_type, td.series, td.number, td.sunat_status
             FROM sales s
             LEFT JOIN tax_document td ON td.tax_document_id = (
                 SELECT t2.tax_document_id FROM tax_document t2
                 WHERE t2.sale_id = s.id AND t2.reason_code IS NULL
                 ORDER BY t2.created_at DESC, t2.tax_document_id DESC
                 LIMIT 1
             )
             WHERE s.cash_session_id = ? AND s.deleted_at IS NULL
             ORDER BY s.issued_at DESC, s.created_at DESC, s.id DESC',
            [$sessionId],
        );

        $paymentsBySale = $this->paymentsOf(array_map(static fn(array $row): string => (string) $row['id'], $rows));

        $total = BigDecimal::zero();
        $count = 0;
        $sales = [];
        foreach ($rows as $row) {
            $id = (string) $row['id'];
            $status = (string) $row['status'];
            if ($status !== self::VOIDED) {
                $total = $total->plus(BigDecimal::of((string) $row['grand_total']));
                ++$count;
            }

            $payments = $paymentsBySale[$id] ?? [];
            $paid = BigDecimal::zero();
            foreach ($payments as $payment) {
                $paid = $paid->plus(BigDecimal::of($payment['amount']));
            }
            // What was left on credit when it was rung up; later collections are not part of it.
            $receivable = (int) $row['receivable_account'] === 1
                ? BigDecimal::of((string) $row['grand_total'])->minus($paid)
                : BigDecimal::zero();

            $sales[] = [
                'id' => $id,
                'issuedAt' => $row['issued_at'] === null
                    ? null
                    : new DateTimeImmutable((string) $row['issued_at'])->format(DATE_ATOM),
                'status' => $status,
                'total' => BigDecimal::of((string) $row['grand_total'])->toScale(
                    2,
                    RoundingMode::HALF_UP,
                )->__toString(),
                'documentType' => $row['document_type'] === null ? null : (string) $row['document_type'],
                'documentNumber' => $row['number'] === null
                    ? null
                    : (string) $row['series'] . '-' . (string) $row['number'],
                'sunatStatus' => $row['sunat_status'] === null ? null : (string) $row['sunat_status'],
                'payments' => $payments,
                'receivable' => ($receivable->isNegative() ? BigDecimal::zero() : $receivable)
                    ->toScale(2, RoundingMode::HALF_UP)
                    ->__toString(),
            ];
        }

        $sold = $this->breakdown->forSession($sessionId);

        return new CashShiftSalesDto(
            $sessionId,
            $count,
            $total->toScale(2, RoundingMode::HALF_UP)->__toString(),
            $sales,
            $sold['methods'],
            $sold['receivable'],
        );
    }

    /**
     * @param list<string> $saleIds
     * @return array<string, list<array{method: string, amount: string}>>
     */
    private function paymentsOf(array $saleIds): array
    {
        if ($saleIds === []) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT sp.sale_id, pm.name, sp.amount
             FROM sale_payments sp
             LEFT JOIN payment_method pm ON pm.id = sp.payment_method_id
             WHERE sp.sale_id IN (?) AND sp.idempotency_key IS NOT NULL
             ORDER BY sp.id',
            [$saleIds],
            [ArrayParameterType::STRING],
        );

        $bySale = [];
        foreach ($rows as $row) {
            $bySale[(string) $row['sale_id']][] = [
                'method' => $row['name'] === null ? 'Sin método' : (string) $row['name'],
                'amount' => BigDecimal::of((string) $row['amount'])->toScale(2, RoundingMode::HALF_UP)->__toString(),
            ];
        }

        return $bySale;
    }
}
