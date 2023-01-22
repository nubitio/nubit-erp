<?php

namespace App\Warehouse\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WarehouseController extends BaseController
{
    #[Route('/warehouse', name: 'warehouse_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('warehouse/index.html.twig');
    }
}
