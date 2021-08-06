<?php

namespace App\Models;

class MesaModel extends DB\SQL\Mapper
{

    protected $db;
    protected $utils;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'mesa');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    public function all()
    {
        return $this->utils->setUp('vw_mesa', 'mesa_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM mesa WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM mesa ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('mesa_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function listafull()
    {
        $result = $this->db->exec("
      SELECT
        m.mesa_id,
        m.descripcion as mesa,
        a.descripcion as ambiente,
        IFNULL(MAX(o.estado), 0) AS estado
      FROM
        mesa m
      INNER JOIN ambiente a ON a.ambiente_id = m.ambiente_id
      LEFT JOIN orden o ON o.mesa_id = m.mesa_id AND o.estado<>3
      WHERE m.estado=1
      GROUP BY m.mesa_id
      ORDER BY o.estado DESC, a.descripcion, m.descripcion
    ");
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
        $this->load(array('mesa_id=?', $id));
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
        $this->load(array('mesa_id=?', $id));
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
        e.razon_social as empresa,
        l.descripcion as local,
        a.descripcion as ambiente,
        m.descripcion as mesa,
        CASE m.estado
        WHEN 1 THEN 'ACTIVO'
        WHEN 0 THEN 'INACTIVO'
        END AS estado
      FROM mesa m
      INNER JOIN ambiente a ON a.ambiente_id = m.ambiente_id
      INNER JOIN local l ON l.local_id = a.local_id
      INNER JOIN empresa e ON e.empresa_id = l.empresa_id
    ");
        return $result;
    }
}

?>