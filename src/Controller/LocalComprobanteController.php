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
        $local = $this->app->get('PARAMS.local');
        $tipo = $this->app->get('PARAMS.tipo');
        $serie = $this->app->get('PARAMS.serie');
        $data = $this->model->edit($local, $tipo, $serie);
        $this->json($data);
    }

    public function delete()
    {
        $this->is_allowed();
        $local = $this->app->get('PARAMS.local');
        $tipo = $this->app->get('PARAMS.tipo');
        $serie = $this->app->get('PARAMS.serie');
        $data = $this->model->delete($local, $tipo, $serie);
        $this->json($data);
    }
}

