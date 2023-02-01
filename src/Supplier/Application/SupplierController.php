<?php

namespace App\Supplier\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SupplierController extends BaseController
{
    #[Route('/supplier', name: 'supplier_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('supplier/index.html.twig');
    }
}
