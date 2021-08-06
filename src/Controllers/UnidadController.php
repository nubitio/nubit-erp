<?php

namespace App\Controllers;

class UnidadController extends BaseController
{

    private $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new UnidadModel();
    }

    public function render()
    {
        $template = new Template;
        echo $template->render('unidad/index.html');
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
        $this->export('Unidades', $data);
    }
}

?>