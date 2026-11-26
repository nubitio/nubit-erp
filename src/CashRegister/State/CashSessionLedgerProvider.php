<?php

declare(strict_types=1);

namespace App\CashRegister\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CashRegister\Dto\CashSessionLedgerDto;
use App\Identity\Auth\BranchContextServiceInterface;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Everything that happened in a shift, in one list. The drawer only records cash, so what was paid by Yape, card
 * or transfer is read from the payments of the sales and merged in: the shift screen shows the whole day, not
 * just the drawer.
 *
 * @implements ProviderInterface<CashSessionLedgerDto>
 */
readonly class CashSessionLedgerProvider implements ProviderInterface
{
    /** Categories whose money leaves the drawer for the safe or the bank rather than being spent. */
    private const array WITHDRAWAL_CATEGORIES = ['manager_withdrawal', 'bank_deposit'];

    /** The newest receipt of a sale; a credit note (reason code) is not the sale's own receipt. */
    private const string LATEST_DOCUMENT = 'SELECT t2.tax_document_id FROM tax_document t2
        WHERE t2.sale_id = %s AND t2.reason_code IS NULL
        ORDER BY t2.created_at DESC, t2.tax_document_id DESC LIMIT 1';

    public function __construct(
        private Connection $connection,
        private BranchContextServiceInterface $branchContext,
    ) {}

    /**
     * @throws Exception|DateMalformedStringException
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CashSessionLedgerDto
    {
        $sessionId = (int) $uriVariables['id'];

        $session = $this->connection->fetchAssociative('SELECT cs.id, cs.cash_register_id, cr.name AS register_name, u.username AS cashier,
                    cs.opened_at, cs.closed_at, cs.status, cs.opening_amount
             FROM cash_sessions cs
             JOIN cash_register cr ON cr.id = cs.cash_register_id
             LEFT JOIN usuario u ON u.usuario_id = cs.opened_by
             WHERE cs.id = ? AND cs.branch_id = ?', [
            $sessionId,
            $this->branchContext->getCurrentBranchId(),
        ]);

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %d.', $sessionId));
        }

        $movements = $this->connection->fetchAllAssociative(
            sprintf('SELECT cm.id, cm.movement_type, cm.amount, cm.description, cm.created_at,
                        cmc.code AS category_code, cmc.name AS category_name, pm.name AS payment_method,
                        td.series, td.number
                 FROM cash_movements cm
                 LEFT JOIN cash_movement_category cmc ON cmc.id = cm.category_id
                 LEFT JOIN payment_method pm ON pm.id = cm.payment_method_id
                 LEFT JOIN tax_document td ON td.tax_document_id = (%s)
                 WHERE cm.cash_session_id = ?', sprintf(self::LATEST_DOCUMENT, 'cm.sale_id')),
            [$sessionId],
        );

        // Cash sales already are drawer movements: only what was paid another way is added here.
        $digitalPayments = $this->connection->fetchAllAssociative(
            sprintf(
                "SELECT sp.id, sp.amount, sp.paid_at, sp.created_at, pm.name AS payment_method, td.series, td.number
                 FROM sale_payments sp
                 JOIN sales s ON s.id = sp.sale_id
                 JOIN payment_method pm ON pm.id = sp.payment_method_id
                 LEFT JOIN tax_document td ON td.tax_document_id = (%s)
                 WHERE s.cash_session_id = ? AND s.deleted_at IS NULL AND s.status <> 'VOIDED'
                   AND sp.idempotency_key IS NOT NULL AND sp.payment_method_id <> 1 AND pm.name <> 'Credito'",
                sprintf(self::LATEST_DOCUMENT, 's.id'),
            ),
            [$sessionId],
        );

        $entries = [];

        foreach ($movements as $row) {
            $entries[] = $this->movementEntry($row);
        }

        foreach ($digitalPayments as $row) {
            $entries[] = [
                'id' => 'p' . (string) $row['id'],
                'at' => (string) ($row['paid_at'] ?? $row['created_at']),
                'kind' => 'sale',
                'document' => $this->document($row),
                'paymentMethod' => (string) $row['payment_method'],
                'description' => 'Venta de mostrador',
                'amount' => $this->money($row['amount']),
            ];
        }

        usort($entries, static fn(array $a, array $b): int => [$b['at'], $b['id']] <=> [$a['at'], $a['id']]);

        return new CashSessionLedgerDto(
            id: (int) $session['id'],
            cashRegisterId: (int) $session['cash_register_id'],
            cashRegisterName: (string) $session['register_name'],
            cashier: $session['cashier'] === null ? null : (string) $session['cashier'],
            openedAt: new DateTimeImmutable((string) $session['opened_at']),
            closedAt: $session['closed_at'] === null ? null : new DateTimeImmutable((string) $session['closed_at']),
            status: $session['status'] === 'OPEN' ? 'open' : 'closed',
            openingAmount: $this->money($session['opening_amount']),
            entries: array_map(
                static function (array $entry): array {
                    // Every entry carries an ISO date so the client does not guess the server's timezone format.
                    $entry['at'] = new DateTimeImmutable($entry['at'])->format(DATE_ATOM);

                    return $entry;
                },
                $entries,
            ),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: string, at: string, kind: string, document: ?string, paymentMethod: ?string, description: string, amount: string}
     */
    private function movementEntry(array $row): array
    {
        $type = (string) $row['movement_type'];
        $amount = BigDecimal::of((string) $row['amount']);

        $kind = match ($type) {
            'OPENING' => 'opening',
            'SALE_IN' => 'sale',
            'CASH_IN' => 'income',
            'CASH_OUT' => in_array($row['category_code'], self::WITHDRAWAL_CATEGORIES, strict: true)
                ? 'withdrawal'
                : 'expense',
            default => 'expense',
        };

        $outflow = in_array($type, ['CASH_OUT', 'REFUND_OUT'], strict: true);

        $description =
            $row['description'] !== null && $row['description'] !== ''
                ? (string) $row['description']
                : (string) ($row['category_name'] ?? 'Movimiento de caja');

        return [
            'id' => 'm' . (string) $row['id'],
            'at' => (string) $row['created_at'],
            'kind' => $kind,
            'document' => $this->document($row),
            // The drawer holds cash: a movement without a payment method of its own is cash.
            'paymentMethod' => (string) ($row['payment_method'] ?? 'Efectivo'),
            'description' => $kind === 'opening' ? 'Fondo inicial del turno' : $description,
            'amount' => $this->money($outflow ? $amount->negated() : $amount),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function document(array $row): ?string
    {
        return ($row['number'] ?? null) === null ? null : (string) $row['series'] . '-' . (string) $row['number'];
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
