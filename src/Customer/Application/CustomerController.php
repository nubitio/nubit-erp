<?php

namespace App\Customer\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CustomerController extends BaseController
{
    #[Route('/customer', name: 'customer_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('customer/index.html.twig');
    }
}
