<?php

namespace App\Model;

class AlmacenModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'almacen');
    }

    public function all()
    {
        return $this->utils->setUp('vw_almacen', 'almacen_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM almacen WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM almacen ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('almacen_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function get_by_local($local)
    {
        return $this->db->exec('select * from almacen where local_id=? and is_default=1 limit 1', array($local));
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
        $this->dao->load(array('almacen_id=?', $id));
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
        $this->dao->load(array('almacen_id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function exportar()
    {
        $result = $this->db->exec("
          SELECT
            e.razon_social as empresa,
            l.descripcion as local,
            a.descripcion as almacen,
            CASE a.tipo_id
            WHEN 1 THEN 'INSUMOS'
            WHEN 2 THEN 'PRODUCTOS'
            END AS tipo,
            CASE l.estado
            WHEN 1 THEN 'ACTIVO'
            WHEN 0 THEN 'INACTIVO'
            END AS estado
          FROM almacen a
          INNER JOIN local l ON l.local_id = a.local_id
          INNER JOIN empresa e ON e.empresa_id = l.empresa_id
        ");
        return $result;
    }
}
