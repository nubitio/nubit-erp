<?php

namespace App\Models;

use DB\SQL\Mapper;

class PaymentTypeModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'payment_type');
    }

    public function get()
    {
        return $this->utils->setUp('payment_type', 'id', 'asc', 'activo=1');
    }

    public function get_list($filtered)
    {
        if ($filtered) {
            $result = $this->db->exec('select * from payment_type where active=1');
        } else {
            $result = $this->db->exec('select * from payment_type');
        }
        return $result;
    }

    public function insert()
    {
        $this->dao->copyfrom('POST');
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function update($id)
    {
        $this->dao->load(array('id=?', $id));
        $this->dao->copyfrom('POST');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
