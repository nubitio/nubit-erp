<?php

namespace App\Controller;

use App\Models\ConcentradosModel;
use Template;

class ConcentradosController extends BaseController
{
    private ConcentradosModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ConcentradosModel();
    }

    public function index()
    {
        $template = new Template();
        echo $template->render('concentrados/index.html');
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

    public function exportar()
    {
        $data = $this->model->exportar();
        $extras = [
            'title' => 'CONCENTRADOS',
            'subtitle' => ''
        ];
        $this->export($data, $extras);
    }
}
