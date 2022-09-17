<?php

namespace App\Model;

class NotifyModel extends BaseModel
{
    public function __construct()
    {
        parent::__construct();
    }

    public function lastSync()
    {
        return $this->db->exec("call sp_last_sync");
    }

    public function get_syncstatus($fecha)
    {
        return $this->db->exec("call sp_notify (?)", [$fecha]);
    }

    public function fill_comprobantes()
    {
        return $this->db->exec("call sp_fill_comprobantes");
    }

    public function saveReport($data)
    {
        try {
            $this->db->exec("insert into log (data) values (?)", [$data]);
            return ["success" => true, "data" => $data, "message" => "registro insertado"];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "message" => $e->getMessage()];
        }
    }
}