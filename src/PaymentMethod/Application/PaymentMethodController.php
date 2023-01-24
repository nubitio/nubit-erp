<?php

namespace App\PaymentMethod\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentMethodController extends BaseController
{
    #[Route('/payment-method', name: 'payment-method_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('payment-method/index.html.twig');
    }
}
