<?php

namespace App\Controller;

use App\Model\EmpleadoModel;
use Template;

class EmpleadoController extends BaseController
{
    private EmpleadoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new EmpleadoModel();
    }

    public function index()
    {
        $template = new Template();
        echo $template->render('empleado/index.html');
    }

    public function get()
    {
        $this->json($this->model->get());
    }

    public function get_list()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $this->json($this->model->get_list($filtered));
    }

    public function insert()
    {
        $this->json($this->model->insert());
    }

    public function update()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->update($id));
    }

    public function delete()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->delete($id));
    }
}
