<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\CashMovement;
use App\Core\Service\PdfExporter;
use App\Entity\CashShift;
use App\Repository\CashMovementRepository;
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
    ) {
    }

    #[Route('/api/v1/cash-shifts/{id}/closure-report', name: 'cash_shift_closure_report', methods: ['GET'])]
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

        $income = 0.0;
        $expense = 0.0;
        foreach ($movements as $movement) {
            if ($movement->getType()->value === 'income') {
                $income += $movement->getAmount() ?? 0;
            } else {
                $expense += $movement->getAmount() ?? 0;
            }
        }

        $openingBalance = $cashShift->getOpeningBalance();
        $expectedAmount = $cashShift->getExpectedAmount() ?? ($openingBalance + $income - $expense);
        $countedAmount = $cashShift->getCountedAmount() ?? $expectedAmount;
        $difference = $cashShift->getDifference() ?? ($countedAmount - $expectedAmount);

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
            'openingBalance' => $openingBalance,
            'income' => $income,
            'expense' => $expense,
            'expectedAmount' => $expectedAmount,
            'countedAmount' => $countedAmount,
            'difference' => $difference,
            'note' => $cashShift->getNote(),
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
