<?php

namespace App\Controllers;

use App\Models\OperacionModel;
use Template;

class OperacionController extends BaseController
{

    private OperacionModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new OperacionModel();
    }

    public function index()
    {
        $template = new Template;
        echo $template->render('operacion/index.html');
    }

    public function get()
    {
        $data = $this->model->all();
        $this->json($data);
    }

    public function lista()
    {
        $tipo = $this->app->get('PARAMS.tipo');
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->model->lista($tipo, $filtered, $extended);
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

    public function exportar()
    {
        $data = $this->model->exportar();
        $extras = [
            'title' => 'LISTA DE TRANSACCIONES',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }
}
