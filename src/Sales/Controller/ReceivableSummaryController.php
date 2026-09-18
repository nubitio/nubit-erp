<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Efact\Domain\Money;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Sales\Repository\SaleRecordRepository;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * KPI header for the "Deudores" page (#409): branch-wide total owed, how many
 * customers carry it, and the single largest debtor. The debtor list itself
 * is `GET /sales/reports/receivable-customers`; this is the one-row summary
 * above it, kept as its own endpoint so the grid's pagination doesn't have to
 * carry branch-wide totals on every page.
 *
 *   GET /sales/reports/receivable-summary
 */
final class ReceivableSummaryController extends AbstractController
{
    public function __construct(
        private readonly SaleRecordRepository $repository,
        private readonly BranchContextServiceInterface $branchContext,
    ) {}

    public function __invoke(): JsonResponse
    {
        $branchId = $this->branchContext->getCurrentBranchId();
        if ($branchId === null) {
            return $this->json([
                'totalBalance' => '0.00',
                'currency' => 'PEN',
                'debtorCount' => 0,
                'overdueTotal' => '0.00',
                'notYetDueTotal' => '0.00',
                'aging' => [],
                'topDebtor' => null,
            ]);
        }

        $totals = $this->repository->sumReceivables();
        $topDebtor = $this->repository->aggregateReceivablesByCustomer(null, 1, 1)[0] ?? null;
        $aging = $this->repository->aggregateReceivablesAging($branchId);

        $overdueTotal = Money::of($aging['1-30'])
            ->plus(Money::of($aging['31-60']))
            ->plus(Money::of($aging['61-90']))
            ->plus(Money::of($aging['90+']));
        $today = new DateTimeImmutable('now', new DateTimeZone('America/Lima'))->format('Y-m-d');

        return $this->json([
            'totalBalance' => $totals['total'],
            'currency' => $totals['currency'],
            'debtorCount' => $totals['debtorCount'],
            'overdueTotal' => Money::toString($overdueTotal),
            'notYetDueTotal' => $aging['current'],
            'aging' => [
                ['bucket' => 'current', 'label' => 'Al día', 'total' => $aging['current']],
                ['bucket' => '1-30', 'label' => '1-30 días', 'total' => $aging['1-30']],
                ['bucket' => '31-60', 'label' => '31-60 días', 'total' => $aging['31-60']],
                ['bucket' => '61-90', 'label' => '61-90 días', 'total' => $aging['61-90']],
                ['bucket' => '90+', 'label' => '+90 días', 'total' => $aging['90+']],
            ],
            'topDebtor' => $topDebtor === null
                ? null
                : [
                    'customerName' => $topDebtor['customerName'],
                    'totalBalance' => $topDebtor['totalBalance'],
                    'isOverdue' => $topDebtor['earliestDueDate'] !== null && $topDebtor['earliestDueDate'] < $today,
                ],
        ]);
    }
}
