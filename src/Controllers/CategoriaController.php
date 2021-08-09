<?php

namespace App\Controllers;

use App\Models\CategoriaModel;

class CategoriaController extends BaseController
{
    private CategoriaModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CategoriaModel();
    }

    public function index()
    {
        $this->render('categoria/index.html.twig');
    }

    public function get()
    {
        $this->json($this->model->get());
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
    