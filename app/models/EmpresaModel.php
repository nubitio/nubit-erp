<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class EmpresaModel extends DB\SQL\Mapper
{

    protected $db;
    protected $utils;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'empresa');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    public function all()
    {
        return $this->utils->setUp('empresa', 'empresa_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM empresa WHERE estado=1 ORDER BY razon_social");
        } else {
            $result = $this->db->exec("SELECT * FROM empresa ORDER BY razon_social");
        }
        if ($extended) {
            array_unshift($result, array('empresa_id' => '', 'razon_social' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function add()
    {
        $this->copyFrom('POST');
        try {
            $this->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->load(array('empresa_id=?', $id));
        $this->copyFrom('POST');
        try {
            $this->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->load(array('empresa_id=?', $id));
        try {
            $this->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function exportar()
    {
        $result = $this->db->exec("
      SELECT
        ruc,
        razon_social,
        domicilio_fiscal,
        telefono,
        aut_sunat,
        CASE estado
        WHEN 1 THEN 'ACTIVO'
        WHEN 0 THEN 'INACTIVO'
        END AS estado
      FROM empresa
    ");
        return $result;
    }
}

?>