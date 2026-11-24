<?php

declare(strict_types=1);

namespace App\CashRegister\Service;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Everything the reports of a shift show, from one place: the X cut (partial, while the shift is open), the Z
 * report (the closing) and the PDF all read this, so they cannot disagree about the same shift. Amounts are decimal
 * strings; what the system has no data for (a shift closed before the closing was recorded in detail) is null.
 *
 * @phpstan-type ShiftCashData array{
 *     opening: string, cashSales: string, income: string, expenses: string, withdrawals: string, expected: string,
 *     counted: ?string, difference: ?string, denominations: ?array<int|string, int>, fundKept: ?bool,
 *     withdrawnAtClose: ?string, note: ?string
 * }
 * @phpstan-type ShiftReportData array{
 *     company: array{name: ?string, ruc: ?string, branch: ?string},
 *     shift: array{id: int, status: string, registerName: string, cashier: ?string, closedBy: ?string, openedAt: ?string, closedAt: ?string},
 *     documents: array{
 *         rows: list<array{type: string, label: string, count: int, ranges: list<string>, taxable: string, igv: string, total: string}>,
 *         count: int, taxable: string, igv: string, total: string
 *     },
 *     sales: array{
 *         total: string,
 *         byMethod: list<array{name: string, operations: int, amount: string, percent: string}>,
 *         credit: array{amount: string, operations: int, percent: string}
 *     },
 *     cash: ShiftCashData,
 *     outflows: list<array{at: string, kind: string, description: string, amount: string}>
 * }
 */
readonly class ShiftReport
{
    public function __construct(
        private Connection $connection,
        private SessionSalesBreakdown $breakdown,
        private ShiftReceipts $receipts,
        private ShiftCash $drawer,
    ) {}

    /**
     * @return ShiftReportData
     * @throws Exception
     */
    public function forSession(int $sessionId): array
    {
        $session = $this->connection->fetchAssociative('SELECT cs.id, cs.status, cs.opening_amount, cs.expected_amount, cs.counted_amount, cs.difference_amount,
                    cs.close_note, cs.opened_at, cs.closed_at, cs.counted_denominations, cs.opening_fund_kept,
                    cs.cash_withdrawn_at_close, cs.branch_id,
                    cr.name AS register_name, u.username AS cashier, cl.username AS closed_by
             FROM cash_sessions cs
             JOIN cash_register cr ON cr.id = cs.cash_register_id
             LEFT JOIN usuario u ON u.usuario_id = cs.opened_by
             LEFT JOIN usuario cl ON cl.usuario_id = cs.closed_by
             WHERE cs.id = ?', [$sessionId]);

        if ($session === false) {
            throw new NotFoundHttpException(sprintf('CashSession not found for id %d.', $sessionId));
        }

        $company = $this->connection->fetchAssociative('SELECT razon_social, ruc FROM empresa LIMIT 1');
        $branch = $this->connection->fetchOne('SELECT descripcion FROM local WHERE local_id = ?', [
            $session['branch_id'],
        ]);

        $sold = $this->breakdown->forSession($sessionId);
        $documents = $this->receipts->forSession($sessionId);
        $cash = $this->drawer->summary($sessionId, $session);
        $isClosed = $session['status'] !== 'OPEN';

        $denominations = $this->denominations($session['counted_denominations']);

        return [
            'company' => [
                'name' => $company === false ? null : (string) $company['razon_social'],
                'ruc' => $company === false ? null : (string) $company['ruc'],
                'branch' => $branch === false ? null : (string) $branch,
            ],
            'shift' => [
                'id' => (int) $session['id'],
                'status' => $isClosed ? 'closed' : 'open',
                'registerName' => (string) $session['register_name'],
                'cashier' => $session['cashier'] === null ? null : (string) $session['cashier'],
                // Shown only when somebody other than the cashier closed it.
                'closedBy' =>
                    $isClosed && $session['closed_by'] !== null && $session['closed_by'] !== $session['cashier']
                        ? (string) $session['closed_by']
                        : null,
                'openedAt' => $this->iso($session['opened_at']),
                'closedAt' => $this->iso($session['closed_at']),
            ],
            'documents' => $documents,
            'sales' => [
                'total' => $sold['total'],
                'byMethod' => array_map(fn(array $method): array => [
                    'name' => $method['name'],
                    'operations' => $method['operations'],
                    'amount' => $method['amount'],
                    'percent' => $this->percent($method['amount'], $sold['total']),
                ], $sold['methods']),
                'credit' => $sold['receivable']
                    + ['percent' => $this->percent($sold['receivable']['amount'], $sold['total'])],
            ],
            'cash' => $cash
                + [
                    'denominations' => $denominations,
                    'fundKept' => $session['opening_fund_kept'] === null
                        ? null
                        : (bool) (int) $session['opening_fund_kept'],
                    'withdrawnAtClose' => $session['cash_withdrawn_at_close'] === null
                        ? null
                        : $this->money($session['cash_withdrawn_at_close']),
                    'note' => $session['close_note'] === null ? null : (string) $session['close_note'],
                ],
            'outflows' => $this->drawer->outflows($sessionId),
        ];
    }

    /**
     * @return array<int|string, int>|null
     */
    private function denominations(mixed $stored): ?array
    {
        if (!is_string($stored) || $stored === '') {
            return null;
        }

        $decoded = json_decode($stored, associative: true);
        if (!is_array($decoded)) {
            return null;
        }

        $quantities = [];
        foreach ($decoded as $cents => $quantity) {
            $quantities[$cents] = is_numeric($quantity) ? (int) $quantity : 0;
        }

        return $quantities;
    }

    private function percent(string $amount, string $total): string
    {
        $whole = BigDecimal::of($total);
        if ($whole->isZero()) {
            return '0.0';
        }

        return BigDecimal::of($amount)->multipliedBy(100)->dividedBy($whole, 1, RoundingMode::HALF_UP)->__toString();
    }

    private function iso(mixed $value): ?string
    {
        return $value === null ? null : new \DateTimeImmutable((string) $value)->format(DATE_ATOM);
    }

    private function money(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HALF_UP)->__toString();
    }
}
