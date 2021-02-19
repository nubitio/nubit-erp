<?php

class NotaController extends BaseController
{
    private $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new NotaModel();
    }

    public function render()
    {
        $template = new Template();
        echo $template->render('nota/index.html');
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
