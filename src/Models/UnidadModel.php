<?php

namespace App\Models;

class UnidadModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'unidad');
    }

    public function all()
    {
        return $this->utils->setUp('unidad', 'unidad_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM unidad WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM unidad ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('unidad_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function add()
    {
        $this->dao->copyFrom('POST');
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->dao->load(array('unidad_id=?', $id));
        $this->dao->copyFrom('POST');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('unidad_id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function exportar()
    {
        return $this->db->exec("
            SELECT
                descripcion as unidad,
                CASE estado WHEN 1 THEN 'ACTIVO' WHEN 0 THEN 'INACTIVO' END AS estado
            FROM unidad
        ");
    }
}
