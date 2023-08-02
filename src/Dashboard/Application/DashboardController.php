<?php

namespace App\Dashboard\Application;

use App\Base\BaseController;
use App\Dashboard\Domain\BillingMonthlyUseCase;
use App\Dashboard\Domain\ProductRotationUseCase;
use App\Dashboard\Domain\SalesDailyUseCase;
use App\Dashboard\Domain\SalesMonthlyUseCase;
use App\Dashboard\Domain\SalesWeeklyUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends BaseController
{
    #[Route('/dashboard', name: 'dashboard_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('dashboard/index.html.twig', [
            'title' => 'Dashboard',
        ]);
    }

    #[Route('/bi/venta-dia', name: 'bi_daily_sales', methods: ['GET'])]
    public function getDailySales(Request $request, SalesDailyUseCase $salesDaily): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        $data = $salesDaily($localId);

        return $this->json($data);
    }

    #[Route('/bi/venta-semana', name: 'bi_weekly_sales', methods: ['GET'])]
    public function getWeeklySales(Request $request, SalesWeeklyUseCase $salesWeekly): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        $data = $salesWeekly($localId);

        return $this->json($data);
    }

    #[Route('/bi/venta-mes', name: 'bi_monthly_sales', methods: ['GET'])]
    public function getMonthlySales(Request $request, SalesMonthlyUseCase $salesMonthly): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        $data = $salesMonthly($localId);

        return $this->json($data);
    }

    #[Route('/bi/monthly-invoicing', name: 'bi_monthly_invoicing', methods: ['GET'])]
    public function getMonthlyInvoicing(Request $request, BillingMonthlyUseCase $monthlyInvoicing): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        $data = $monthlyInvoicing($localId);

        return $this->json($data);
    }

    #[Route('/bi/product-rotation/{lapse}', name: 'bi_product_rotation', methods: ['GET'])]
    public function getProductRotation(Request $request, ProductRotationUseCase $productRotation, string $lapse): Response
    {
        /** @var string $localId */
        $localId = $request->getSession()->get('branch_id');
        $data = $productRotation($localId, $lapse);

        return $this->json($data);
    }
}
