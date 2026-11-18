<?php

declare(strict_types=1);

namespace App\BankReconciliation\Controller;

use App\AccountsPayable\Entity\SupplierPayment;
use App\BankReconciliation\Service\BankReconciliationMatchingService;
use App\BankReconciliation\Service\TreasuryReconciliationSource;
use App\CashRegister\Entity\CashMovement;
use App\Sales\Entity\SalePayment;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /api/v1/bank-reconciliation/unreconciled-movements
 * Feeds the right-hand pane of the two-pane reconciliation screen: the
 * three source movement types (SupplierPayment/SalePayment/CashMovement)
 * that don't yet have a BankReconciliationMatch. Doesn't map to a single
 * ApiResource collection since it spans three unrelated entities.
 */
#[Route(
    '/api/v1/bank-reconciliation/unreconciled-movements',
    name: 'bank_reconciliation_unreconciled_movements',
    methods: ['GET'],
)]
#[IsGranted('perm:bank_reconciliation.view')]
final class UnreconciledMovementsController extends AbstractController
{
    public function __construct(
        private readonly BankReconciliationMatchingService $matchingService,
        private readonly TreasuryReconciliationSource $treasury,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));

        // With Treasury on, one list replaces the three old sources: the movements of the bank account's own
        // Treasury account. Without it (or before the account has one) the old lists are returned unchanged.
        $bankAccountId = $request->query->get('bankAccountId');
        $bankAccountId = is_string($bankAccountId) && $bankAccountId !== '' ? $bankAccountId : null;
        $treasuryActive = $bankAccountId === null
            ? $this->treasury->anyBankAccount()
            : $this->treasury->activeFor($bankAccountId);
        if ($treasuryActive) {
            return $this->json([
                'supplierPayments' => [],
                'salePayments' => [],
                'cashMovements' => [],
                'treasury' => [
                    'active' => true,
                    'movements' => $this->treasury->unreconciled($bankAccountId, $from, $to, 100),
                ],
            ]);
        }

        $supplierPayments = array_map(
            static fn(SupplierPayment $p): array => [
                'id' => $p->getId(),
                'amount' => $p->getAmount(),
                'date' => $p->getPaidAt()->format('Y-m-d'),
                'label' =>
                    'Pago proveedor: '
                        . ($p->getReference() ?? $p->getSupplierBill()->getSupplier()->getBusinessName()),
            ],
            $this->matchingService->unreconciledSupplierPayments($from, $to),
        );

        $salePayments = array_map(
            fn(SalePayment $p): array => [
                'id' => $p->getId(),
                'amount' => $p->getAmount(),
                'date' => $p->getPaidAt()?->format('Y-m-d'),
                'label' => $this->matchingService->formatSaleLabel($p->getSaleRecord()),
            ],
            $this->matchingService->unreconciledSalePayments($from, $to),
        );

        $cashMovements = array_map(
            static fn(CashMovement $cm): array => [
                'id' => $cm->getId(),
                'amount' => $cm->getAmount(),
                'date' => $cm->getCreatedAt()->format('Y-m-d'),
                'label' => $cm->getDescription(),
            ],
            $this->matchingService->unreconciledCashMovements($from, $to),
        );

        return $this->json([
            'supplierPayments' => $supplierPayments,
            'salePayments' => $salePayments,
            'cashMovements' => $cashMovements,
            'treasury' => ['active' => false, 'movements' => []],
        ]);
    }

    private function parseDate(?string $raw): ?DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return new DateTimeImmutable($raw);
    }
}
