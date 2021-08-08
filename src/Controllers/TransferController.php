<?php

namespace App\Controllers;

use App\Models\TransferModel;
use Template;

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
        $template = new Template();
        echo $template->render('transfer/index.html');
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
