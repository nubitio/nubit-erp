<?php

namespace App\Controllers;

use App\Models\CajaModel;
use Template;

class CajaController extends BaseController
{
    private CajaModel $caja;

    function __construct()
    {
        parent::__construct();
        $this->caja = new CajaModel();
    }

    function render()
    {
        $template = new Template;
        echo $template->render('caja/index.html');
    }

    function consulta()
    {
        $template = new Template;
        echo $template->render('caja-consulta/index.html');
    }

    function check()
    {
        $this->json($this->caja->getCurrentActiveCashBox());
    }

    function get()
    {
        $data = $this->caja->all();
        $this->json($data);
    }

    function detalle()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->caja->detalle($id);
        $this->json($data);
    }

    function add()
    {
        $data = $this->caja->add();
        $this->json($data);
    }

    function flujo()
    {
        $data = $this->caja->flujo();
        $this->json($data);
    }

    function edit()
    {
        $uid = $this->app->get('PARAMS.uid');
        $caja_id = $this->app->get('PARAMS.caja_id');
        $data = $this->caja->edit($uid, $caja_id);
        $this->json($data);
    }

    function delete()
    {
        $uid = $this->app->get('PARAMS.uid');
        $caja_id = $this->app->get('PARAMS.caja_id');
        $data = $this->caja->delete($uid, $caja_id);
        $this->json($data);
    }

    function cerrar()
    {
        $data = $this->caja->cerrar();
        $this->json($data);
    }

    public function reporte()
    {
        $id = $this->app->get('PARAMS.id');
        $header = $this->caja->getOneById($id);
        $detail = $this->caja->getDetailById($id);

        $this->app->set('title', 'Reporte de caja');
        $logo_path = $this->app->get('ASSETS') . 'images/logo.png';
        $this->app->set('logo', $this->base64($logo_path));
        $this->app->set('header', $header);
        $this->app->set('detail', $detail);
        $template = new Template;
        //echo $template->render('templates/invoice.html');
        $this->report($template->render('templates/cashbox.html'));
    }
}
