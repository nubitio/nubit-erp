<?php

namespace App\Controllers;

class DescuentoController extends BaseController
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new DescuentoModel($this->db);
    }

    public function render()
    {
        $template = new Template;
        echo $template->render('descuento/index.html');
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
}