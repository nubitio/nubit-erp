<?php

namespace App\Controllers;

use App\Models\MenuModel;
use Template;

class MenuController extends BaseController
{

    private MenuModel $menu;

    public function __construct()
    {
        parent::__construct();
        $this->menu = new MenuModel();
    }

    public function render()
    {
        $template = new Template;
        echo $template->render('menu/index.html');
    }

    public function get()
    {
        $data = $this->menu->all();
        $this->json($data);
    }

    public function lista()
    {
        $filtered = (bool)$this->app->get('PARAMS.filtered');
        $extended = (bool)$this->app->get('PARAMS.extended');
        $data = $this->menu->lista($filtered, $extended);
        $this->json($data);
    }

    public function padres()
    {
        $data = $this->menu->padres();
        $this->json($data);
    }

    public function add()
    {
        $data = $this->menu->add();
        $this->json($data);
    }

    public function edit()
    {
        $menu_id = $this->app->get('PARAMS.menu_id');
        $data = $this->menu->edit($menu_id);
        $this->json($data);
    }

    public function delete()
    {
        $menu_id = $this->app->get('PARAMS.menu_id');
        $data = $this->menu->delete($menu_id);
        $this->json($data);
    }
}
