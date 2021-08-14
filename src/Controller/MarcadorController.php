<?php

namespace App\Controller;

use App\Models\AsistenciaModel;
use Template;

class MarcadorController extends BaseController
{
    private AsistenciaModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new AsistenciaModel();
    }

    public function beforeroute()
    {
        // do nothing
    }

    public function index()
    {
        $template = new Template();
        echo $template->render('marcador/index.html');
    }

    public function time()
    {
        echo date('H:i');
    }

    public function registrar()
    {
        $_POST['fecha'] = date('Y-m-d');
        $_POST['hora'] = date('H:i:s');
        $this->json($this->model->insert());
    }
}