<?php

namespace App\VoucherSeries\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoucherSeriesController extends BaseController
{
    #[Route('/voucher-series', name: 'voucher-series_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('voucher-series/index.html.twig');
    }
}
