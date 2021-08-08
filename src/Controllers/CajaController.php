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

    function index()
    {
        $this->render('caja/index.html.twig');
    }

    function consulta()
    {
        $this->render('caja-consulta/index.html.twig');
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
        $logo_path = $this->app->get('ASSETS') . 'images/logo.png';

        $content = $this->renderView('reportes/cashbox.html.twig', [
            'title' => 'Reporte de caja',
            'logo' => $this->base64($logo_path),
            'header' => $header,
            'detail' => $detail
        ]);
        $this->report($content);
    }
}
