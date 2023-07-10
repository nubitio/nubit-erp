<?php

namespace App\Transfer\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransferController extends BaseController
{
    #[Route('/transfer', name: 'transfer_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('transfer/index.html.twig');
    }
}
