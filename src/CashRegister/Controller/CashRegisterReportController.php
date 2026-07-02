<?php

declare(strict_types=1);

namespace App\CashRegister\Controller;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Nubit\Platform\Export\PdfExporter;
use Nubit\Platform\Feature\Attribute\RequiresFeature;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class CashRegisterReportController extends AbstractController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly PdfExporter $pdfExporter,
    ) {}

    /**
     * @throws Exception
     */
    #[RequiresFeature('pdf_bulk_download')]
    #[Route('/api/cash-sessions/{id}/closure-report', name: 'cash_session_closure_report', methods: ['GET'])]
    #[Route('/api/cash-shifts/{id}/closure-report', name: 'cash_shift_closure_report', methods: ['GET'])]
    public function __invoke(int $id): Response
    {
        // --- 1. Resolve the v2 session: by id first, then by legacy_cash_shift_id ---
        $sessionRow = $this->connection->fetchAssociative(<<<SQL
            SELECT
                cs.id            AS sessionId,
                cs.opening_amount,
                cs.expected_amount,
                cs.counted_amount,
                cs.difference_amount,
                cs.close_note    AS note,
                cs.opened_at,
                cs.closed_at,
                u.username       AS openedBy,
                cr.id            AS cashRegisterId,
                cr.name          AS cashRegisterName
            FROM cash_sessions cs
            JOIN cash_register cr ON cr.id = cs.cash_register_id
            JOIN usuario u        ON u.usuario_id = cs.opened_by
            WHERE cs.id = ?
            SQL, [$id]);

        if ($sessionRow === false) {
            // Fallback: treat $id as a legacy cash_shift id
            $sessionRow = $this->connection->fetchAssociative(<<<SQL
                SELECT
                    cs.id            AS sessionId,
                    cs.opening_amount,
                    cs.expected_amount,
                    cs.counted_amount,
                    cs.difference_amount,
                    cs.close_note    AS note,
                    cs.opened_at,
                    cs.closed_at,
                    u.username       AS openedBy,
                    cr.id            AS cashRegisterId,
                    cr.name          AS cashRegisterName
                FROM cash_sessions cs
                JOIN cash_register cr ON cr.id = cs.cash_register_id
                JOIN usuario u        ON u.usuario_id = cs.opened_by
                WHERE cs.legacy_cash_shift_id = ?
                SQL, [$id]);
        }

        if ($sessionRow === false) {
            return $this->json(['message' => 'Turno no encontrado'], Response::HTTP_NOT_FOUND);
        }

        /** @var int $sessionId */
        $sessionId = (int) $sessionRow['sessionId'];

        // --- 2. Fetch movements from v2 table ---
        $movementRows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT
                cm.movement_type,
                cm.amount,
                cm.description,
                cm.created_at,
                cmc.name AS category_name
            FROM cash_movements cm
            LEFT JOIN cash_movement_category cmc ON cmc.id = cm.category_id
            WHERE cm.cash_session_id = ?
              AND cm.movement_type IN ('CASH_IN', 'CASH_OUT', 'SALE_IN')
            ORDER BY cm.created_at ASC, cm.id ASC
            SQL, [$sessionId]);

        // --- 3. Aggregate income / expense ---
        $income = BigDecimal::zero();
        $expense = BigDecimal::zero();

        $movements = [];
        foreach ($movementRows as $row) {
            $movementType = (string) $row['movement_type'];
            $amount = BigDecimal::of((string) ($row['amount'] ?? '0'));

            if ($movementType === 'CASH_OUT') {
                $expense = $expense->plus($amount);
                $type = 'expense';
            } else {
                // SALE_IN or CASH_IN
                $income = $income->plus($amount);
                $type = 'income';
            }

            $createdAtRaw = $row['created_at'];
            $movements[] = [
                'createdAt' => $createdAtRaw !== null ? new DateTimeImmutable((string) $createdAtRaw) : null,
                'category' => $row['category_name'],
                'type' => $type,
                'description' => $row['description'],
                'amount' => $row['amount'],
            ];
        }

        // --- 4. Sales by payment method breakdown ---
        $salesByPaymentMethodRows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT pm.name AS paymentMethod, SUM(cm.amount) AS total
            FROM cash_movements cm
            JOIN payment_method pm ON pm.id = cm.payment_method_id
            WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
            GROUP BY cm.payment_method_id, pm.name
            SQL, [$sessionId]);

        $salesByPaymentMethod = array_map(static fn(array $row): array => [
            'paymentMethod' => (string) $row['paymentMethod'],
            'total' => number_format((float) $row['total'], 2, '.', ''),
        ], $salesByPaymentMethodRows);

        // --- 5. Build financial summary ---
        $openingBalance = BigDecimal::of((string) ($sessionRow['opening_amount'] ?? '0'));

        $expectedAmount = $sessionRow['expected_amount'] !== null
            ? BigDecimal::of((string) $sessionRow['expected_amount'])
            : $openingBalance->plus($income)->minus($expense);

        $countedAmount = $sessionRow['counted_amount'] !== null
            ? BigDecimal::of((string) $sessionRow['counted_amount'])
            : $expectedAmount;

        $difference = $sessionRow['difference_amount'] !== null
            ? BigDecimal::of((string) $sessionRow['difference_amount'])
            : $countedAmount->minus($expectedAmount);

        $openingBalanceStr = $openingBalance->toScale(2, RoundingMode::HALF_UP)->__toString();
        $incomeStr = $income->toScale(2, RoundingMode::HALF_UP)->__toString();
        $expenseStr = $expense->toScale(2, RoundingMode::HALF_UP)->__toString();
        $expectedAmountStr = $expectedAmount->toScale(2, RoundingMode::HALF_UP)->__toString();
        $countedAmountStr = $countedAmount->toScale(2, RoundingMode::HALF_UP)->__toString();
        $differenceStr = $difference->toScale(2, RoundingMode::HALF_UP)->__toString();

        $openedAtRaw = $sessionRow['opened_at'];
        $closedAtRaw = $sessionRow['closed_at'];

        $content = $this->twig->render('cash_register/closure_report.html.twig', [
            'cashRegister' => [
                'id' => (int) $sessionRow['cashRegisterId'],
                'name' => (string) $sessionRow['cashRegisterName'],
            ],
            'cashShift' => [
                'id' => $sessionId,
                'openedAt' => $openedAtRaw !== null ? new DateTimeImmutable((string) $openedAtRaw) : null,
                'closedAt' => $closedAtRaw !== null ? new DateTimeImmutable((string) $closedAtRaw) : null,
                'openedBy' => (string) $sessionRow['openedBy'],
            ],
            'openingBalance' => $openingBalanceStr,
            'income' => $incomeStr,
            'expense' => $expenseStr,
            'expectedAmount' => $expectedAmountStr,
            'countedAmount' => $countedAmountStr,
            'difference' => $differenceStr,
            'note' => $sessionRow['note'],
            'salesByPaymentMethod' => $salesByPaymentMethod,
            'movements' => $movements,
        ]);

        return $this->pdfExporter->export($content, sprintf('cierre-caja-%d', $sessionId));
    }
}
