<?php

namespace App\Model;

use DB\SQL\Mapper;

class EmpleadoModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'empleado');
    }

    public function get()
    {
        return $this->utils->setUp('empleado', 'id', 'asc', 'activo=1');
    }

    public function get_list($filtered)
    {
        if ($filtered) {
            $result = $this->db->exec("select dni, concat(nombres, ' ', apellidos) as empleado from empleado where activo=1");
        } else {
            $result = $this->db->exec("select dni, concat(nombres, ' ', apellidos) as empleado from empleado");
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
}
