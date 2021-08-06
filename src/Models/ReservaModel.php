<?php

namespace App\Models;

class ReservaModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new DB\SQL\Mapper($this->db, 'reserva');
    }

    public function get()
    {
        return $this->utils->setUp('vw_reserva', 'reserva_id', 'desc');
    }

    public function get_list($filtered)
    {
        if ($filtered) {
            $result = $this->db->exec('select * from reserva where cancelado = 0');
        } else {
            $result = $this->db->exec('select * from reserva');
        }
        return $result;
    }

    public function insert()
    {
        $this->dao->copyfrom('POST');
        $uuid = $this->db->exec("SELECT UuidToBin(UUID()) AS uuid");
        $this->dao->reserva_id = $uuid[0]['uuid'];
        $this->dao->local_id = $this->current_local_id;
        $this->dao->created_by = $this->current_user_id;
        $this->dao->created_at = date('Y-m-d H:i:s');
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function update($id)
    {
        $this->dao->load(array('reserva_id = UuidToBin(?)', $id));
        $this->dao->copyfrom('POST');
        $this->dao->updated_by = $this->current_user_id;
        $this->dao->updated_at = date('Y-m-d');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('reserva_id = UuidToBin(?)', $id));
        $this->dao->cancelado = 1;
        $this->dao->updated_by = $this->current_user_id;
        $this->dao->updated_at = date('Y-m-d');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro anulado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
