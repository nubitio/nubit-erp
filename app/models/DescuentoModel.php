<?php

class DescuentoModel extends DB\SQL\Mapper
{
    protected $db;
    protected $utils;

    public function __construct(\DB\SQL $db)
    {
        parent::__construct($db, 'descuento');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    public function all()
    {
        return $this->utils->setUp('descuento', 'date_created');
    }

    public function detalle($id)
    {
        $result = $this->db->exec("
          SELECT
            dd.*,
            p.descripcion as producto
          FROM
            descuento_detalle dd
          INNER JOIN producto p ON p.producto_id = dd.producto_id
          WHERE
            dd.descuento_id = '$id'
        ");
        return $result;
    }

    public function add()
    {
        try {
            $this->db->begin();
            $key = $this->db->exec("SELECT UUID() AS uuid");
            $uuid = $key[0]['uuid'];
            $this->copyfrom('POST');
            $this->descuento_id = $uuid;
            $this->save();
            $query = 'INSERT INTO descuento_detalle (`descuento_id`, `producto_id`, `precio_lista`, `precio_descuento`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $uuid . "', '" . $detalle['producto'][$i] . "', " . $detalle['precio_lista'][$i] . "," . $detalle['precio_descuento'][$i] . ")";
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
        $this->load(array('descuento_id=?', $id));
        try {
            $this->db->begin();
            $this->copyfrom('POST');
            $this->save();
            $this->db->exec('DELETE from descuento_detalle WHERE descuento_id=?', $id);
            $query = 'INSERT INTO descuento_detalle (`descuento_id`, `producto_id`, `precio_lista`, `precio_descuento`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $id . "', '" . $detalle['producto'][$i] . "', " . $detalle['precio_lista'][$i] . "," . $detalle['precio_descuento'][$i] . ")";
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
        $this->load(array('descuento_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'DELETE from descuento_detalle WHERE descuento_id=?',
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
}