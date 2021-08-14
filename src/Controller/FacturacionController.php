<?php

namespace App\Controller;

use Template;

class FacturacionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $template = new Template;
        echo $template->render('contabilidad/facturacion/index.html');
    }
}