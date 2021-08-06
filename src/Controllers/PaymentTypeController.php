<?php

namespace App\Controllers;

use App\Models\PaymentTypeModel;
use Template;

class PaymentTypeController extends BaseController
{
    private PaymentTypeModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new PaymentTypeModel();
    }

    public function render()
    {
        $template = new Template();
        echo $template->render('payment_type/index.html');
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
