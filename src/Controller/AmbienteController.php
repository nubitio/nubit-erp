<?php

namespace App\Controller;

use App\Models\AmbienteModel;
use Template;

class AmbienteController extends BaseController
{

    private AmbienteModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new AmbienteModel($this->db);
    }

    public function index()
    {
        $template = new Template;
        echo $template->render('ambiente/index.html');
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
        $local = $this->app->get('PARAMS.local');
        $data = $this->model->cargar($local);
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
            'title' => 'LISTA DE AMBIENTES',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }
}