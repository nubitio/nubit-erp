<?php

namespace App\Controllers;

class MainController extends BaseController
{

    private $menu;
    private $local;
    private $almacen;

    public function __construct()
    {
        parent::__construct();
        $this->menu = new MenuModel();
        $this->local = new LocalModel();
        $this->almacen = new AlmacenModel();
    }

    public function beforeroute()
    {
        //
    }

    function render()
    {
        if ($this->check_local()) {
            $rol_id = $this->app->get('SESSION.rol_id');
            $menus = $this->menu->lista_rol($rol_id);
            $this->app->set('menus', $menus);
            $this->app->set('view', 'dashboard/index.html');
            $template = new Template;
            echo $template->render('layout/index.html');
        } else {
            $this->app->clear('SESSION');
            $this->app->reroute('/login');
        }
    }

    function check_local()
    {
        if (!empty($this->app->get('SESSION.local_id'))) {
            return true;
        } else {
            return false;
        }
    }

    function set_local()
    {
        $local_id = $this->app->get('PARAMS.local');
        if (!empty($local_id)) {
            $local = $this->local->get_by_id($local_id);
            $almacen = $this->almacen->get_by_local($local_id);
            $this->app->set('SESSION.local_id', $local_id);
            $this->app->set('SESSION.local', $local[0]['descripcion']);
            $this->app->set('SESSION.default_almacen', $almacen[0]['almacen_id']);
            $data = [
                "usuario_id" => $this->app->get('SESSION.usuario_id'),
                "usuario" => $this->app->get('SESSION.usuario'),
                "rol_id" => $this->app->get('SESSION.rol_id'),
                "local_id" => $this->app->get('SESSION.local_id'),
                "local" => $this->app->get('SESSION.local'),
                "almacen_id" => $this->app->get('SESSION.default_almacen')
            ];
            $this->json(array('success' => true, "data" => $data));
        } else {
            $this->json(array('success' => false));
        }
    }

    function dashboard()
    {
        $template = new Template;
        echo $template->render('dashboard/index.html');
    }

    function get_menu()
    {
        $rol = $this->app->get('SESSION.rol_id');
        $result = $this->menu->lista_rol($rol);
        if ($result["success"]) {
            $data = [];
            $i = 0;
            foreach ($result["data"] as $menu) {
                if ($menu['parent_id'] == 0 && $menu['link'] == '') {
                    $submenu = [];
                    $j = 0;
                    foreach ($result["data"] as $option) {
                        if ($menu['menu_id'] == $option['parent_id']) {
                            $submenu[$j] = ["text" => $option['descripcion'], "iconCls" => $option['icon'], "url" => $option['link']];
                            $j++;
                        }
                    }
                    $data[$i] = ["text" => $menu['descripcion'], "iconCls" => $menu['icon'], "children" => $submenu];
                    $i++;
                } else {
                    if ($menu['parent_id'] == 0 && $menu['link'] != '') {
                        $data[$i] = ["text" => $menu['descripcion'], "iconCls" => $menu['icon'], "url" => $menu['link']];
                        $i++;
                    }
                }
            }
            $this->json($data);
        } else {
            $this->app->error("500", $result["msg"]);
        }
    }

    public function getTheme()
    {
        $primaryColor = $this->options->getByName('app_theme_primary_color');
        $primaryColorDark = $this->options->getByName('app_theme_primary_color_dark');
        $accentColor = $this->options->getByName('app_theme_accent_color');
        $secondaryColor = $this->options->getByName('app_theme_secondary_color');

        $this->json(['success' => true, 'data' => ['primary_color' => $primaryColor, 'primary_color_dark' => $primaryColorDark, 'accent_color' => $accentColor, 'secondary_color' => $secondaryColor], 'msg' => '']);
    }
}