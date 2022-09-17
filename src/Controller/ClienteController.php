<?php

namespace App\Controller;

use App\Model\ClienteModel;

class ClienteController extends BaseController
{

    private ClienteModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ClienteModel();
    }

    public function index()
    {
        $this->render('cliente/index.html.twig');
    }

    public function get()
    {
        $data = $this->model->all();
        $this->json($data);
    }

    public function getone()
    {
        $ruc = $this->app->get('PARAMS.ruc');
        $data = $this->model->getone($ruc);
        $this->json($data[0]);
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->model->lista($filtered, $extended);
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
            'title' => 'REPORTE DE CLIENTES',
            'subtitle' => 'DE RESTAURANTE'
        ];
        $this->export($data, $extras);
    }
}
