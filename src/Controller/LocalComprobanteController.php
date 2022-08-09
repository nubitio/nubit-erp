<?php

namespace App\Controller;

use App\Model\LocalComprobanteModel;
use Template;

class LocalComprobanteController extends BaseController
{

    private LocalComprobanteModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new LocalComprobanteModel();
    }

    public function index()
    {
        $this->render('local-comprobante/index.html.twig');
    }

    public function get()
    {
        $data = $this->model->all();
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
}

