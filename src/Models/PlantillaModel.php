<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class PlantillaModel extends DB\SQL\Mapper
{

    protected $db;
    protected $utils;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'plantilla');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    public function all()
    {
        return $this->utils->setUp('plantilla', 'plantilla_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM plantilla WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM plantilla ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('plantilla_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
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
        plantilla_detalle pd
      INNER JOIN producto p ON p.producto_id = pd.producto_id
      WHERE
        pd.plantilla_id = '$id'
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
                'INSERT INTO plantilla VALUES (?,?,?,?,?,?,?)',
                array(
                    1 => $uuid,
                    2 => $_POST['descripcion'],
                    3 => $_POST['estado'],
                    4 => 1,
                    5 => date('Y-m-d H:i:s'),
                    6 => 1,
                    7 => date('Y-m-d H:i:s')
                )
            );
            $last = $uuid;
            $query = 'INSERT INTO plantilla_detalle (`plantilla_id`, `producto_id`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $last . "', '" . $detalle['producto'][$i] . "')";
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
        $this->load(array('plantilla_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'UPDATE plantilla SET descripcion=?, estado=? WHERE plantilla_id=?',
                array(
                    1 => $_POST['descripcion'],
                    2 => $_POST['estado'],
                    3 => $id
                )
            );
            $this->db->exec(
                'DELETE from plantilla_detalle WHERE plantilla_id=?',
                array(
                    1 => $id
                )
            );
            $query = 'INSERT INTO plantilla_detalle (`plantilla_id`, `producto_id`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $id . "', '" . $detalle['producto'][$i] . "')";
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
        $this->load(array('plantilla_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'DELETE from plantilla_detalle WHERE plantilla_id=?',
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
      FROM plantilla_detalle pd
      INNER JOIN producto p ON p.producto_id = pd.producto_id
      WHERE pd.plantilla_id = '$id'
    ");
        return $result;
    }
}

?>