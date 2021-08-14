<?php

namespace App\Controller;

use App\Model\RecetaModel;
use Template;

class RecetaController extends BaseController
{

    private RecetaModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecetaModel($this->db);
    }

    public function index()
    {
        $template = new Template;
        echo $template->render('receta/index.html');
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
        $data = $this->model->reporte($id);
        $this->export('Receta', $data);
    }
}
