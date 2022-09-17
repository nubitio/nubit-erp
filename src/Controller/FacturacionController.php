<?php

namespace App\Controller;

class FacturacionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->render('contabilidad/facturacion/index.html.twig', []);
    }
}