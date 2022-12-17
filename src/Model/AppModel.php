<?php

namespace App\Model;

class AppModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function get_ambient_list()
    {
        try {
            $data = $this->db->exec("call sp_app_select_ambient");
            return ["success" => true, "data" => $data, "msg" => ""];
        } catch (\Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    public function get_tables_by_ambient($ambient_id)
    {
        try {
            $data = $this->db->exec("call sp_app_select_tables_by_ambient(?)", [$ambient_id]);
            return ["success" => true, "data" => $data, "msg" => ""];
        } catch (\Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    public function get_category_list()
    {
        try {
            $data = $this->db->exec("call sp_app_select_category");
            return ["success" => true, "data" => $data, "msg" => ""];
        } catch (\Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    public function get_products_by_category($category_id)
    {
        try {
            $data = $this->db->exec("call sp_app_select_products_by_category(?)", [$category_id]);
            return ["success" => true, "data" => $data, "msg" => ""];
        } catch (\Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    public function change_order_table($order, $table)
    {
        try {
            $data = $this->db->exec("call sp_app_change_order_table(?, ?)", [$order, $table]);
            return ["success" => true, "data" => $data, "msg" => ""];
        } catch (\Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }
}