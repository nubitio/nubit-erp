<?php

namespace App\Controller;

use App\Models\ReservaModel;
use Template;

class ReservaController extends BaseController
{
    private ReservaModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ReservaModel();
    }

    public function index()
    {
        $template = new Template();
        echo $template->render('reserva/index.html');
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
