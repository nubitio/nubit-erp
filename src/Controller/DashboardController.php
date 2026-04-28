<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Security\Auth\BranchContextService;
use App\Sales\Repository\DashboardRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardRepository $repository,
        private readonly BranchContextService $branchContext,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->attributes->get('type');
        $view = strtolower((string) $request->query->get('view', ''));
        $lite = filter_var($request->query->get('lite', false), FILTER_VALIDATE_BOOL);
        $isLite = $lite || $view === 'mobile';

        $branchId = $this->branchContext->getCurrentBranchId();

        if (!$branchId) {
            return $this->json([]);
        }

        if ($type !== 'overview') {
            return $this->json([]);
        }

        try {
            $data = $isLite
                ? $this->repository->getLiteOverview($branchId)
                : $this->repository->getOverview($branchId);
        } catch (\Throwable $e) {
            $this->logger->error('Dashboard overview fatal error', [
                'branch_id' => $branchId,
                'mode' => $isLite ? 'lite' : 'full',
                'exception' => $e->getMessage(),
                'class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            if ($isLite) {
                return $this->json([
                    'summary' => [
                        'salesToday' => 0.0,
                        'salesYesterday' => 0.0,
                        'salesWeek' => 0.0,
                        'dailyGrowth' => null,
                        'invoicesToday' => 0,
                        'avgTicketToday' => 0.0,
                        'refundsToday' => 0.0,
                        'netSalesToday' => 0.0,
                    ],
                    'paymentMethodsToday' => [],
                    'hourlySalesToday' => [],
                    'recentSalesToday' => [],
                    'meta' => [
                        'mode' => 'lite',
                        'partial' => true,
                        'failedSegments' => ['fatal'],
                    ],
                ]);
            }

            return $this->json([
                'summary' => [],
                'salesByDay' => [],
                'salesByMonth' => [],
                'paymentMethods' => [],
                'paymentMethodTrend' => [
                    'methods' => [],
                    'points' => [],
                ],
                'topProducts' => [],
                'purchasesSummary' => [
                    'purchasesToday' => 0.0,
                    'purchasesWeek' => 0.0,
                    'purchasesThisMonth' => 0.0,
                    'purchasesLastMonth' => 0.0,
                    'receiptsToday' => 0,
                    'receiptsThisMonth' => 0,
                    'monthGrowth' => null,
                ],
                'purchasesByDay' => [],
                'topPurchasedProducts' => [],
                'topCustomers' => [],
                'voucherTypes' => [],
                'lowStockProducts' => [],
                'meta' => [
                    'mode' => 'full',
                    'partial' => true,
                    'failedSegments' => ['fatal'],
                ],
            ]);
        }

        return $this->json($data);
    }
}
