<?php

namespace App\Models;

use DB\SQL\Mapper;

class ConcentradosModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'concentrados');
    }

    public function get()
    {
        return $this->utils->setUp('concentrados', 'id', 'asc', );
    }

    public function get_list($filtered)
    {
        if ($filtered) {
            $result = $this->db->exec('select * from concentrados where activo=1');
        } else {
            $result = $this->db->exec('select * from concentrados');
        }
        return $result;
    }

    public function insert()
    {
        $this->dao->copyfrom('POST');
        $this->dao->created_by = $this->user_id;
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
        $this->dao->updated_by = $this->user_id;
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

    public function exportar()
    {
        return $this->db->exec('select s.*, c.razon_social from concentrados s inner join cliente c on c.ruc = s.cliente');
    }
}
