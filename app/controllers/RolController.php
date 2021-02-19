<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class RolController extends BaseController
{

    private $rol;

    public function __construct()
    {
        parent::__construct();
        $this->rol = new RolModel($this->db);
    }

    public function render()
    {
        $template = new Template;
        echo $template->render('rol/index.html');
    }

    public function get()
    {
        $data = $this->rol->all();
        $this->json($data);
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->rol->lista($filtered, $extended);
        $this->json($data);
    }

    public function add()
    {
        $data = $this->rol->add();
        $this->json($data);
    }

    public function edit()
    {
        $rol_id = $this->app->get('PARAMS.rol_id');
        $data = $this->rol->edit($rol_id);
        $this->json($data);
    }

    public function delete()
    {
        $rol_id = $this->app->get('PARAMS.rol_id');
        $data = $this->rol->delete($rol_id);
        $this->json($data);
    }

    public function get_mr()
    {
        if (isset($_POST['rol_id'])) {
            $result = $this->rol->get_mr($_POST['rol_id']);
            if ($result["success"]) {
                $data = [];
                $i = 0;
                foreach ($result["data"] as $menu) {
                    if ($menu['parent_id'] == 0) {
                        $submenu = [];
                        $j = 0;
                        foreach ($result["data"] as $option) {
                            if ($menu['menu_id'] == $option['parent_id']) {
                                $submenu[$j] = ["id" => $option['menu_id'], "text" => $option['descripcion'], "checked" => (bool)$option['checked']];
                                $j++;
                            }
                        }
                        $data[$i] = ["id" => $menu['menu_id'], "text" => $menu['descripcion'], "children" => $submenu];
                        $i++;
                    }
                }
                $this->json($data);
            } else {
                $this->app->error("500", $result["msg"]);
            }
        } else {
            $this->json([]);
        }
    }

    public function set_mr()
    {
        $id = $this->app->get('PARAMS.id');
        $result = $this->rol->set_mr($id, $_POST['permisos']);
        $this->json($result);
    }
}
