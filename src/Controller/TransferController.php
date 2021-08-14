<?php

namespace App\Controller;

use App\Model\TransferModel;

class TransferController extends BaseController
{
    private TransferModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new TransferModel();
    }

    public function index()
    {
        $this->render('transfer/index.html.twig');
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
