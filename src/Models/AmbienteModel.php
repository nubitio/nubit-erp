<?php

namespace App\Models;

class AmbienteModel extends DB\SQL\Mapper
{

    protected $db;
    protected $utils;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'ambiente');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    public function all()
    {
        return $this->utils->setUp('vw_ambiente', 'ambiente_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM ambiente WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM ambiente ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('ambiente_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function cargar($id)
    {
        $result = $this->db->exec("SELECT * FROM ambiente WHERE local_id='$id' AND estado=1 ORDER BY descripcion");
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
        $this->load(array('ambiente_id=?', $id));
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
        $this->load(array('ambiente_id=?', $id));
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
        CASE a.estado
        WHEN 1 THEN 'ACTIVO'
        WHEN 0 THEN 'INACTIVO'
        END AS estado
      FROM ambiente a
      INNER JOIN local l ON l.local_id = a.local_id
      INNER JOIN empresa e ON e.empresa_id = l.empresa_id
    ");
        return $result;
    }
}

?>