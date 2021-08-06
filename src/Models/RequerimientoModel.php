<?php

namespace App\Models;

class RequerimientoModel extends DB\SQL\Mapper
{

    protected $db;
    protected $utils;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'requerimiento');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    public function all()
    {
        return $this->utils->setUp('vw_requerimiento', 'fecha', 'DESC');
    }

    public function detalle($id)
    {
        $result = $this->db->exec("
      SELECT
        rd.*,
        p.descripcion as producto
      FROM
        requerimiento_detalle rd
      INNER JOIN producto p ON p.producto_id = rd.producto_id
      WHERE
        rd.requerimiento_id = '$id'
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
                'INSERT INTO requerimiento VALUES (?,?,?,?)',
                array(
                    1 => $uuid,
                    2 => $_POST['fecha'],
                    3 => $_POST['almacen_id'],
                    4 => 1,
                )
            );
            $last = $uuid;
            $query = 'INSERT INTO requerimiento_detalle (`requerimiento_id`, `producto_id`, `cantidad`) VALUES ';
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
        $this->load(array('requerimiento_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'UPDATE requerimiento SET fecha=?, almacen_id=? WHERE requerimiento_id=?',
                array(
                    1 => $_POST['fecha'],
                    2 => $_POST['almacen_id'],
                    3 => $id
                )
            );
            $this->db->exec(
                'DELETE from requerimiento_detalle WHERE requerimiento_id=?',
                array(
                    1 => $id
                )
            );
            $query = 'INSERT INTO requerimiento_detalle (`requerimiento_id`, `producto_id`, `cantidad`) VALUES ';
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
        $this->load(array('requerimiento_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'DELETE from requerimiento_detalle WHERE requerimiento_id=?',
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
        p.descripcion as producto,
        rd.cantidad,
        p.precio_compra,
        (p.precio_compra*rd.cantidad)as costo_requerimiento
      FROM requerimiento_detalle rd
      INNER JOIN producto p ON p.producto_id = rd.producto_id
      INNER JOIN requerimiento r on r.requerimiento_id=rd.requerimiento_id 
      WHERE rd.requerimiento_id = '$id'
    ");
        return $result;
    }
}

?>