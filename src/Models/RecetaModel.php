<?php

namespace App\Models;

class RecetaModel extends DB\SQL\Mapper
{

    protected $db;
    protected $utils;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'receta');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    public function all()
    {
        return $this->utils->setUp('vw_receta', 'receta_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM receta WHERE estado=1 ORDER BY producto_id");
        } else {
            $result = $this->db->exec("SELECT * FROM receta ORDER BY producto_id");
        }
        if ($extended) {
            array_unshift($result, array('receta_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function detalle($id)
    {
        $result = $this->db->exec("
      SELECT
        pd.*,
        p.descripcion as producto
      FROM
        receta_detalle pd
      INNER JOIN producto p ON p.producto_id = pd.producto_id
      WHERE
        pd.receta_id = '$id'
    ");
        return $result;
    }

    public function add()
    {
        try {
            $this->db->begin();
            $key = $this->db->exec("SELECT UUID() AS uuid");
            $uuid = $key[0]['uuid'];
            $this->db->exec(
                'INSERT INTO receta VALUES (?,?,?,?,?,?,?)',
                array(
                    1 => $uuid,
                    2 => $_POST['producto_id'],
                    3 => 1,
                    4 => 1,
                    5 => date('Y-m-d H:i:s'),
                    6 => 1,
                    7 => date('Y-m-d H:i:s')
                )
            );
            $last = $uuid;
            $query = 'INSERT INTO receta_detalle (`receta_id`, `producto_id`, `cantidad`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $last . "', '" . $detalle['producto'][$i] . "', '" . $detalle['cantidad'][$i] . "')";
            }
            $this->db->exec($query .= implode(',', $query_parts));
            $this->db->commit();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->load(array('receta_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'UPDATE receta SET producto_id=? WHERE receta_id=?',
                array(
                    1 => $_POST['producto_id'],
                    2 => $id
                )
            );
            $this->db->exec(
                'DELETE from receta_detalle WHERE receta_id=?',
                array(
                    1 => $id
                )
            );
            $query = 'INSERT INTO receta_detalle (`receta_id`, `producto_id`, `cantidad`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $id . "', '" . $detalle['producto'][$i] . "', '" . $detalle['cantidad'][$i] . "')";
            }
            $this->db->exec($query .= implode(',', $query_parts));
            $this->db->commit();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->load(array('receta_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'DELETE from receta_detalle WHERE receta_id=?',
                array(
                    1 => $id
                )
            );
            $this->erase();
            $this->db->commit();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function reporte($id)
    {
        $result = $this->db->exec("
      SELECT
        p.descripcion as producto
      FROM receta_detalle pd
      INNER JOIN producto p ON p.producto_id = pd.producto_id
      WHERE pd.receta_id = '$id'
    ");
        return $result;
    }
}

?>