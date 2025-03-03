<?php

namespace App\Dashboard\Application;

use App\Core\Application\Session\Security;
use App\Dashboard\Domain\BillingMonthlyUseCase;
use App\Dashboard\Domain\ProductRotationUseCase;
use App\Dashboard\Domain\SalesDailyUseCase;
use App\Dashboard\Domain\SalesMonthlyUseCase;
use App\Dashboard\Domain\SalesWeeklyUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SalesDailyUseCase $salesDaily,
        private readonly SalesWeeklyUseCase $salesWeekly,
        private readonly SalesMonthlyUseCase $salesMonthly,
        private readonly BillingMonthlyUseCase $monthlyInvoicing,
        private readonly ProductRotationUseCase $productRotation,
    ) {
    }

    public function __invoke(Request $request, Security $security): Response
    {
        $type = $request->attributes->get('type');
        $user = $security->connectedUser();
        $localId = $user->branchId;

        switch ($type) {
            case 'venta-dia':
                $data = ($this->salesDaily)($localId);
                break;
            case 'venta-semana':
                $data = ($this->salesWeekly)($localId);
                break;
            case 'venta-mes':
                $data = ($this->salesMonthly)($localId);
                break;
            case 'monthly-invoicing':
                $data = ($this->monthlyInvoicing);
                break;
            case 'product-rotation':
                $lapse = $request->attributes->get('lapse');
                $data = ($this->productRotation)($localId, $lapse);
                break;
            default:
                $data = [];
        }

        return $this->json($data);
    }
}
