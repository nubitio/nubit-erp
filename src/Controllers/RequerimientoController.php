<?php

namespace App\Controllers;

use App\Models\RequerimientoModel;
use Template;

class RequerimientoController extends BaseController
{

    private RequerimientoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RequerimientoModel();
    }

    public function index()
    {
        $template = new Template;
        echo $template->render('requerimiento/index.html');
    }

    public function get()
    {
        $data = $this->model->all();
        $this->json($data);
    }

    function detalle()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->detalle($id);
        $this->json($data);
    }

    public function add()
    {
        $data = $this->model->add();
        $this->json($data);
    }

    public function edit()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->edit($id);
        $this->json($data);
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $data = $this->model->delete($id);
        $this->json($data);
    }

    public function reporte()
    {
        $id = $this->app->get('PARAMS.id');
        $fecha = $this->app->get('PARAMS.fecha');
        $data = $this->model->reporte($id);
        $extras = [
            'title' => 'LISTA DE REQUERIMIENT0',
            'subtitle' => 'DE LA FECHA ' . $fecha
        ];
        $this->export($data, $extras);
    }
}
