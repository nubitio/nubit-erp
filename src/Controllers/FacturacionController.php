<?php

namespace App\Controllers;

class FacturacionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function render()
    {
        $template = new Template;
        echo $template->render('contabilidad/facturacion/index.html');
    }
}