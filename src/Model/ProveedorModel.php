<?php

namespace App\Model;

class ProveedorModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'proveedor');
    }

    public function all()
    {
        return $this->utils->setUp('proveedor', 'razon_social', 'ASC');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM proveedor WHERE estado=1 ORDER BY razon_social");
        } else {
            $result = $this->db->exec("SELECT * FROM proveedor ORDER BY razon_social");
        }
        if ($extended) {
            array_unshift($result, array('proveedor_id' => '', 'razon_social' => 'TODOS', 'estado' => '1'));
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
        $this->dao->load(array('proveedor_id=?', $id));
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
        $this->dao->load(array('proveedor_id=?', $id));
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
                ruc,
                razon_social,
                domicilio_fiscal,
                telefono,
                CASE estado WHEN 1 THEN 'ACTIVO' WHEN 2 THEN 'INACTIVO' else '' END AS estado
            FROM proveedor
        ");
    }
}
