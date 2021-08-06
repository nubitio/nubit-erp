<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class PermisoController extends BaseController
{

    private $permiso;

    public function __construct()
    {
        parent::__construct();
        $this->permiso = new PermisoModel($this->db);
    }

    public function render()
    {
        $template = new Template;
        echo $template->render('permiso/index.html');
    }

    public function get()
    {
        $data = $this->permiso->all();
        $this->json($data);
    }

    public function lista()
    {
        $data = $this->permiso->lista();
        $this->json($data);
    }

    public function add()
    {
        $data = $this->permiso->add();
        $this->json($data);
    }

    public function edit()
    {
        $permiso_id = $this->app->get('PARAMS.permiso_id');
        $data = $this->permiso->edit($permiso_id);
        $this->json($data);
    }

    public function delete()
    {
        $permiso_id = $this->app->get('PARAMS.permiso_id');
        $data = $this->permiso->delete($permiso_id);
        $this->json($data);
    }
}

?>