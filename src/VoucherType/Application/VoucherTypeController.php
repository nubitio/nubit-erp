<?php

namespace App\VoucherType\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoucherTypeController extends BaseController
{
    #[Route('/voucher-type', name: 'voucher-type_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('voucher-type/index.html.twig');
    }
}
