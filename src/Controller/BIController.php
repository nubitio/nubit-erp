<?php

namespace App\Controller;

use App\Models\BIModel;

class BIController extends BaseController
{

    private BIModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new BIModel();
    }

    public function venta_dia()
    {
        $this->json($this->model->venta_dia());
    }

    public function venta_semana()
    {
        $this->json($this->model->venta_semana());
    }

    public function venta_mes()
    {
        $this->json($this->model->venta_mes());
    }

    public function productRotation()
    {
        $lapse = $this->app->get('PARAMS.lapse');
        $this->json($this->model->productRotation($lapse));
    }
}
