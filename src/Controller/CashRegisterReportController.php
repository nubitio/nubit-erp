<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Service\PdfExporter;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateMalformedStringException;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class CashRegisterReportController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @throws Exception|DateMalformedStringException
     */
    #[Route('/api/cash-shifts/{id}/closure-report', name: 'cash_shift_closure_report', methods: ['GET'])]
    public function __invoke(int $id): Response
    {
        // Load session from cash_sessions v2 (id is cash_session id)
        $session = $this->connection->fetchAssociative(
            <<<'SQL'
            SELECT
                cs.id,
                cs.opening_amount,
                cs.closing_amount,
                cs.opened_at,
                cs.closed_at,
                cs.note,
                cs.status,
                u.username AS cashier_username,
                cr.id AS cash_register_id,
                cr.name AS cash_register_name
            FROM cash_sessions cs
            JOIN cash_register cr ON cr.id = cs.cash_register_id
            LEFT JOIN usuario u ON u.usuario_id = cs.cashier_id
            WHERE cs.id = ?
            SQL,
            [$id]
        );

        if ($session === false) {
            return $this->json(['message' => 'Turno no encontrado'], Response::HTTP_NOT_FOUND);
        }

        // Load all movements from cash_movements v2 ordered by date
        $movementRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                cm.id,
                cm.movement_type,
                cm.amount,
                cm.description,
                cm.created_at,
                cat.nombre AS category_name
            FROM cash_movements cm
            LEFT JOIN category cat ON cat.id = cm.category_id
            WHERE cm.cash_session_id = ?
              AND cm.movement_type IN ('CASH_IN', 'CASH_OUT')
            ORDER BY cm.created_at ASC, cm.id ASC
            SQL,
            [$id]
        );

        // Aggregate CASH_IN and CASH_OUT
        $income  = BigDecimal::zero();
        $expense = BigDecimal::zero();
        foreach ($movementRows as $row) {
            if ($row['movement_type'] === 'CASH_IN') {
                $income = $income->plus(BigDecimal::of((string) ($row['amount'] ?? '0')));
            } else {
                $expense = $expense->plus(BigDecimal::of((string) ($row['amount'] ?? '0')));
            }
        }

        // SALE_IN total from cash_movements v2
        $saleInRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements
             WHERE cash_session_id = ? AND movement_type = 'SALE_IN'",
            [$id]
        );
        $saleIn = BigDecimal::of((string) ($saleInRaw ?: '0'));

        // Sales grouped by payment method for breakdown
        $salesByPaymentMethodRows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT pm.name AS paymentMethod, SUM(cm.amount) AS total
            FROM cash_movements cm
            JOIN payment_method pm ON pm.id = cm.payment_method_id
            WHERE cm.cash_session_id = ? AND cm.movement_type = 'SALE_IN'
            GROUP BY cm.payment_method_id, pm.name
            SQL,
            [$id]
        );

        $salesByPaymentMethod = array_map(
            static fn (array $row): array => [
                'paymentMethod' => (string) $row['paymentMethod'],
                'total' => number_format((float) $row['total'], 2, '.', ''),
            ],
            $salesByPaymentMethodRows
        );

        $openingBalance = BigDecimal::of((string) $session['opening_amount']);

        // expectedAmount = opening + SALE_IN + CASH_IN - CASH_OUT
        $expectedAmount = $openingBalance->plus($saleIn)->plus($income)->minus($expense);

        // countedAmount = closing_amount if set, else expectedAmount
        $countedAmount = $session['closing_amount'] !== null
            ? BigDecimal::of((string) $session['closing_amount'])
            : $expectedAmount;

        $difference = $countedAmount->minus($expectedAmount);

        $openingBalanceStr = $openingBalance->toScale(2, RoundingMode::HALF_UP)->__toString();
        $incomeStr         = $income->toScale(2, RoundingMode::HALF_UP)->__toString();
        $expenseStr        = $expense->toScale(2, RoundingMode::HALF_UP)->__toString();
        $expectedAmountStr = $expectedAmount->toScale(2, RoundingMode::HALF_UP)->__toString();
        $countedAmountStr  = $countedAmount->toScale(2, RoundingMode::HALF_UP)->__toString();
        $differenceStr     = $difference->toScale(2, RoundingMode::HALF_UP)->__toString();

        $content = $this->twig->render('cash_register/closure_report.html.twig', [
            'cashRegister' => [
                'id'   => (int) $session['cash_register_id'],
                'name' => $session['cash_register_name'],
            ],
            'cashShift' => [
                'id'       => (int) $session['id'],
                'openedAt' => $session['opened_at'] ? new DateTimeImmutable($session['opened_at']) : null,
                'closedAt' => $session['closed_at'] ? new DateTimeImmutable($session['closed_at']) : null,
                'openedBy' => $session['cashier_username'] ?? '',
            ],
            'openingBalance'      => $openingBalanceStr,
            'income'              => $incomeStr,
            'expense'             => $expenseStr,
            'expectedAmount'      => $expectedAmountStr,
            'countedAmount'       => $countedAmountStr,
            'difference'          => $differenceStr,
            'note'                => $session['note'],
            'salesByPaymentMethod' => $salesByPaymentMethod,
            'movements'           => array_map(static fn (array $row): array => [
                'createdAt'   => $row['created_at'] ? new DateTimeImmutable($row['created_at']) : null,
                'category'    => $row['category_name'],
                'type'        => $row['movement_type'] === 'CASH_IN' ? 'income' : 'expense',
                'description' => $row['description'],
                'amount'      => $row['amount'],
            ], $movementRows),
        ]);

        $pdfExporter = new PdfExporter();

        return $pdfExporter->export($content, sprintf('cierre-caja-%d', (int) $session['id']));
    }
}
