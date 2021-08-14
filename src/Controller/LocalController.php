<?php

namespace App\Controller;

use App\Models\LocalModel;
use Template;

class LocalController extends BaseController
{

    private LocalModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new LocalModel();
    }

    public function index()
    {
        $this->render('local/index.html.twig');
    }

    public function get()
    {
        $data = $this->model->all();
        $this->json($data);
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->model->lista($filtered, $extended);
        $this->json($data);
    }

    public function cargar()
    {
        $empresa = $this->app->get('PARAMS.empresa');
        $data = $this->model->cargar($empresa);
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
            'title' => 'LISTA DE LOCALES',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }
}
