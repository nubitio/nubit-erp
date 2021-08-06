<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class LocalModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'local');
    }

    public function all()
    {
        return $this->utils->setUp('vw_local', 'local_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM local WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM local ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('local_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function get_by_id($id)
    {
        return $this->db->exec('select * from local where local_id=?', $id);
    }

    public function cargar($id)
    {
        return $this->db->exec("SELECT * FROM local WHERE empresa_id='$id' AND estado=1 ORDER BY descripcion");
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
        $this->dao->load(array('local_id=?', $id));
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
        $this->dao->load(array('local_id=?', $id));
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
                e.razon_social as empresa,
                l.descripcion as local,
                l.direccion,
                l.telefono,
                CASE l.estado
                WHEN 1 THEN 'ACTIVO'
                WHEN 0 THEN 'INACTIVO'
                END AS estado
            FROM local l
            INNER JOIN empresa e ON e.empresa_id = l.empresa_id
        ");
    }
}
