<?php

namespace App\Controller;

use App\Model\ProductoModel;
use Template;

class ProductoController extends BaseController
{

    private ProductoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ProductoModel();
    }

    public function index()
    {
        $this->render('producto/index.html.twig');
    }

    public function getAll()
    {
        $this->json($this->model->all(2));
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
