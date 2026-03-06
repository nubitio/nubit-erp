<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CashMovement;
use App\Core\Service\PdfExporter;
use App\Entity\CashShift;
use App\Repository\CashMovementRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class CashRegisterReportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CashMovementRepository $cashMovementRepository,
        private readonly Environment $twig,
        private readonly Connection $connection,
    ) {
    }

    #[Route('/api/cash-shifts/{id}/closure-report', name: 'cash_shift_closure_report', methods: ['GET'])]
    public function __invoke(int $id): Response
    {
        /** @var CashShift|null $cashShift */
        $cashShift = $this->entityManager->find(CashShift::class, $id);
        if (!$cashShift instanceof CashShift) {
            return $this->json(['message' => 'Turno no encontrado'], Response::HTTP_NOT_FOUND);
        }

        $cashRegister = $cashShift->getCashRegister();

        $movements = $this->cashMovementRepository->findBy(
            ['cashShift' => $cashShift],
            ['createdAt' => 'ASC', 'id' => 'ASC']
        );

        $income = BigDecimal::zero();
        $expense = BigDecimal::zero();
        foreach ($movements as $movement) {
            if ($movement->getType()->value === 'income') {
                $income = $income->plus(BigDecimal::of((string) ($movement->getAmount() ?? '0')));
            } else {
                $expense = $expense->plus(BigDecimal::of((string) ($movement->getAmount() ?? '0')));
            }
        }

        // Include V2 cash sale movements (written to cash_movements via cash_sessions)
        $v2SalesIncomeRaw = $this->connection->fetchOne(
            "SELECT COALESCE(SUM(cm2.amount), 0)
             FROM cash_movements cm2
             JOIN cash_sessions cs ON cs.id = cm2.cash_session_id
             WHERE cs.legacy_cash_shift_id = ? AND cm2.movement_type = 'SALE_IN'",
            [$id]
        );
        $income = $income->plus(BigDecimal::of((string) ($v2SalesIncomeRaw ?: '0')));

        // V2 sales grouped by payment method for report breakdown
        $salesByPaymentMethodRows = $this->connection->fetchAllAssociative(
            "SELECT pm.name AS paymentMethod, SUM(cm.amount) AS total
             FROM cash_movements cm
             JOIN cash_sessions cs ON cs.id = cm.cash_session_id
             JOIN payment_method pm ON pm.id = cm.payment_method_id
             WHERE cs.legacy_cash_shift_id = ? AND cm.movement_type = 'SALE_IN'
             GROUP BY cm.payment_method_id, pm.name",
            [$id]
        );
        $salesByPaymentMethod = array_map(
            static fn (array $row): array => [
                'paymentMethod' => (string) $row['paymentMethod'],
                'total' => number_format((float) $row['total'], 2, '.', ''),
            ],
            $salesByPaymentMethodRows
        );

        $openingBalance = BigDecimal::of($cashShift->getOpeningBalance());
        $expectedAmount = $cashShift->getExpectedAmount() !== null
            ? BigDecimal::of($cashShift->getExpectedAmount())
            : $openingBalance->plus($income)->minus($expense);
        $countedAmount = $cashShift->getCountedAmount() !== null
            ? BigDecimal::of($cashShift->getCountedAmount())
            : $expectedAmount;
        $difference = $cashShift->getDifference() !== null
            ? BigDecimal::of($cashShift->getDifference())
            : $countedAmount->minus($expectedAmount);

        $openingBalanceStr = $openingBalance->toScale(2, RoundingMode::HALF_UP)->__toString();
        $incomeStr = $income->toScale(2, RoundingMode::HALF_UP)->__toString();
        $expenseStr = $expense->toScale(2, RoundingMode::HALF_UP)->__toString();
        $expectedAmountStr = $expectedAmount->toScale(2, RoundingMode::HALF_UP)->__toString();
        $countedAmountStr = $countedAmount->toScale(2, RoundingMode::HALF_UP)->__toString();
        $differenceStr = $difference->toScale(2, RoundingMode::HALF_UP)->__toString();

        $content = $this->twig->render('cash_register/closure_report.html.twig', [
            'cashRegister' => [
                'id' => $cashRegister->getId(),
                'name' => $cashRegister->getName(),
            ],
            'cashShift' => [
                'id' => $cashShift->getId(),
                'openedAt' => $cashShift->getOpenedAt(),
                'closedAt' => $cashShift->getClosedAt(),
                'openedBy' => $cashShift->getOpenedBy()->getUsername(),
            ],
            'openingBalance' => $openingBalanceStr,
            'income' => $incomeStr,
            'expense' => $expenseStr,
            'expectedAmount' => $expectedAmountStr,
            'countedAmount' => $countedAmountStr,
            'difference' => $differenceStr,
            'note' => $cashShift->getNote(),
            'salesByPaymentMethod' => $salesByPaymentMethod,
            'movements' => array_map(static fn (CashMovement $movement): array => [
                'createdAt' => $movement->getCreatedAt(),
                'category' => $movement->getCategory()?->getName(),
                'type' => $movement->getType()->value,
                'description' => $movement->getDescription(),
                'amount' => $movement->getAmount(),
            ], $movements),
        ]);

        $pdfExporter = new PdfExporter();

        return $pdfExporter->export($content, sprintf('cierre-caja-%d', $cashShift->getId()));
    }
}
