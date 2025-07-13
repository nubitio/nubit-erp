<?php

namespace App\Dashboard\Application;

use App\Core\Application\Session\Security;
use App\Dashboard\Domain\DashboardRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository,
    ) {
    }

    public function __invoke(Request $request, Security $security): Response
    {
        $type = $request->attributes->get('type');
        $user = $security->connectedUser();

        if (!$user) {
            return $this->json([]);
        }

        $localId = $user->branchId;

        switch ($type) {
            case 'venta-dia':
                $data = $this->repository->getDailySales($localId);
                // Convert 'venta' to float for each item
                foreach ($data as $key => $value) {
                    $data[$key]['venta'] = (float)$value['venta'];
                }
                break;
            case 'venta-semana':
                $data = $this->repository->getWeeklySales($localId);
                // Convert 'venta' to float for each item
                foreach ($data as $key => $value) {
                    $data[$key]['venta'] = (float)$value['venta'];
                }
                break;
            case 'venta-mes':
                $data = $this->repository->getMonthlySales($localId);
                // Convert 'venta' to float for each item
                foreach ($data as $key => $value) {
                    $data[$key]['venta'] = (float)$value['venta'];
                }
                break;
            case 'monthly-invoicing':
                $data = $this->repository->getMonthlyInvoicing();
                break;
            default:
                $data = [];
        }

        return $this->json($data);
    }
}
