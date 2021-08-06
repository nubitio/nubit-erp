<?php


class AppController extends BaseController
{

    private $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new AppModel();
    }

    public function get_ambient_list()
    {
        $this->json($this->model->get_ambient_list());
    }

    public function get_tables_by_ambient()
    {
        $ambient_id = $this->app->get('PARAMS.id');
        $this->json($this->model->get_tables_by_ambient($ambient_id));
    }

    public function get_category_list()
    {
        $this->json($this->model->get_category_list());
    }

    public function get_products_by_category()
    {
        $category_id = $this->app->get('PARAMS.id');
        $this->json($this->model->get_products_by_category($category_id));
    }

    public function change_order_table()
    {
        $order = $this->app->get('PARAMS.order');
        $table = $this->app->get('PARAMS.table');
        $this->json($this->model->change_order_table($order, $table));
    }
}