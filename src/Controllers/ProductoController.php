<?php

namespace App\Controllers;

class ProductoController extends BaseController
{

    private $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ProductoModel();
    }

    public function render_insumos()
    {
        $template = new Template;
        echo $template->render('insumo/index.html');
    }

    public function render_productos()
    {
        $template = new Template();
        echo $template->render('producto/index.html');
    }

    public function get_insumos()
    {
        $data = $this->model->all(1);
        $this->json($data);
    }

    public function get_productos()
    {
        $data = $this->model->all(2);
        $this->json($data);
    }

    public function get_all()
    {
        $this->json($this->model->get_all(2));
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->model->lista($filtered, $extended);
        $this->json($data);
    }

    public function listafull()
    {
        $data = $this->model->listafull();
        $this->json($data);
    }

    public function get_precios()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->get_precios($id));
    }

    public function add_insumo()
    {
        $data = $this->model->add(1);
        $this->json($data);
    }

    public function add_producto()
    {
        $data = $this->model->add(2);
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

    public function exportar_insumos()
    {
        $data = $this->model->exportar_insumos();
        $extras = [
            'title' => 'REPORTE DE INSUMOS',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }

    public function exportar_productos()
    {
        $data = $this->model->exportar_productos();
        $extras = [
            'title' => 'REPORTE DE PRODUCTOS',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }
}
