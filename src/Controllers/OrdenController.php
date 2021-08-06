<?php

namespace App\Controllers;

use App\Models\OrdenModel;
use Template;

class OrdenController extends BaseController
{

    private OrdenModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new OrdenModel();
    }

    public function render()
    {
        $template = new Template;
        echo $template->render('orden/index.html');
    }

    public function get()
    {
        $data = $this->model->all();
        $this->json($data);
    }

    public function get_one()
    {
        $mesa_id = $this->app->get('PARAMS.mesa_id');
        $data = $this->model->get_one($mesa_id);
        $this->json($data);
    }

    public function get_detalle()
    {
        $orden_id = $this->app->get('PARAMS.orden_id');
        $data = $this->model->get_detalle($orden_id);
        $this->json($data);
    }

    public function get_detalle_destino()
    {
        $orden_id = $this->app->get('PARAMS.orden_id');
        $destino_id = $this->app->get('PARAMS.destino_id');
        $data = $this->model->get_detalle_destino($orden_id, $destino_id);
        $this->json($data);
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->model->lista($filtered, $extended);
        $this->json($data);
    }

    public function listas()
    {
        $destino_id = $this->app->get('PARAMS.destino_id');
        $data = $this->model->listas($destino_id);
        $this->json($data);
    }

    public function add()
    {
        $data = $this->model->add();
        $this->json($data);
    }

    public function addweb()
    {
        $data = $this->model->addweb();
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

    public function anular()
    {
        $id = $this->app->get('PARAMS.id');
        $this->json($this->model->anular($id));
    }

    public function add_detalle()
    {
        $data = $this->model->add_detalle();
        $this->json($data);
    }

    public function edit_detalle()
    {
        $orden_id = $this->app->get('PARAMS.orden_id');
        $producto_id = $this->app->get('PARAMS.producto_id');
        $data = $this->model->edit_detalle($orden_id, $producto_id);
        $this->json($data);
    }

    public function delete_detalle()
    {
        $orden_id = $this->app->get('PARAMS.orden_id');
        $producto_id = $this->app->get('PARAMS.producto_id');
        $data = $this->model->delete_detalle($orden_id, $producto_id);
        $this->json($data);
    }

    public function event()
    {
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        echo "data: true";
        flush();
    }

    public function exportar()
    {
        $fi = $this->app->get('PARAMS.fi');
        $ff = $this->app->get('PARAMS.ff');
        $data = $this->model->exportar($fi, $ff);
        $extras = [
            'title' => 'REPORTE DE COMANDAS',
            'subtitle' => 'PERIODO ' . $fi . ' AL ' . $ff,
        ];
        $this->export($data, $extras);
    }
}
