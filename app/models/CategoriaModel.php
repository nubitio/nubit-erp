<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class CategoriaModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'categoria');
    }

    public function all()
    {
        return $this->utils->setUp('categoria', 'categoria_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM categoria WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM categoria ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('categoria_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
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
        $this->dao->load(array('categoria_id=?', $id));
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
        $this->dao->load(array('categoria_id=?', $id));
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
                descripcion as categoria,
                CASE estado WHEN 1 THEN 'ACTIVO' WHEN 0 THEN 'INACTIVO' END AS estado
            FROM categoria
        ");
    }
}
