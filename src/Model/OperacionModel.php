<?php

namespace App\Model;

class OperacionModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'operacion');
    }

    public function all()
    {
        return $this->utils->setUp('vw_operacion', 'operacion_id');
    }

    public function lista($tipo_id, $filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM operacion WHERE tipo_id=$tipo_id AND estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM operacion WHERE tipo_id=$tipo_id ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('operacion_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
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
        $this->dao->load(array('operacion_id=?', $id));
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
        $this->dao->load(array('operacion_id=?', $id));
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
                descripcion as transaccion,
                CASE tipo_id WHEN 1 THEN 'INGRESO' WHEN 2 THEN 'SALIDA' END AS tipo,
                CASE estado WHEN 1 THEN 'ACTIVO' WHEN 0 THEN 'INACTIVO' END AS estado
            FROM operacion
        ");
    }
}
