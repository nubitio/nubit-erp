<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class OrdenModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new DB\SQL\Mapper($this->db, 'orden');
    }

    public function all()
    {
        return $this->utils->setUp('vw_orden', 'orden_id', 'DESC');
    }

    public function get_one($mesa_id)
    {
        return $this->db->exec("SELECT * FROM vw_orden WHERE mesa_id='$mesa_id' AND estado<>3");
    }

    public function get_detalle($orden_id)
    {
        return $this->db->exec("
          SELECT
            od.*,
            p.descripcion AS producto,
            p.destino_id,
            CASE WHEN p.destino_id = 1 THEN 'COCINA' WHEN p.destino_id = 2 THEN 'BARRA' ELSE 'COCINA/BARRA' END AS destino,
            o.estado_cocina,
            o.estado_barra
          FROM orden_detalle od
          INNER JOIN orden o ON o.orden_id = od.orden_id
          INNER JOIN producto p ON od.producto_id = p.producto_id
          WHERE od.orden_id = ?
          AND od.estado != 2
          ORDER BY od.date_created
      ", $orden_id);
    }

    public function get_detalle_destino($orden_id, $destino_id)
    {
        return $this->db->exec("
          SELECT
            od.*,
            p.descripcion AS producto,
            p.destino_id,
            CASE WHEN p.destino_id = 1 THEN 'COCINA' WHEN p.destino_id = 2 THEN 'BARRA' ELSE 'COCINA/BARRA' END AS destino
          FROM
            orden_detalle od
          INNER JOIN producto p ON od.producto_id = p.producto_id
          WHERE
            od.orden_id=$orden_id
            AND od.estado != 2
            AND (p.destino_id=$destino_id OR p.destino_id=3)
            AND (CASE WHEN p.destino_id = 3 THEN 0 ELSE od.estado END) != 1
        ");
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM orden WHERE estado=1 ORDER BY orden_id");
        } else {
            $result = $this->db->exec("SELECT * FROM orden ORDER BY orden_id");
        }
        if ($extended) {
            array_unshift($result, array('orden_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function listas($destino_id)
    {
        if ($destino_id == 1) {
            $where = "o.estado_cocina = 0";
        } else {
            $where = "o.estado_barra = 0";
        }
        $result = $this->db->exec("
          SELECT
            o.*
          FROM
            vw_orden o
          INNER JOIN orden_detalle od ON od.orden_id = o.orden_id
          INNER JOIN producto p ON p.producto_id = od.producto_id
          WHERE
            (p.destino_id = $destino_id OR p.destino_id = 3)
            AND $where
            AND o.estado=2
          GROUP BY o.orden_id
          ORDER BY o.fecha DESC
        ");
        return $result;
    }

    public function add()
    {
        $this->dao->copyfrom('POST');
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function addweb()
    {
        $this->dao->copyfrom('POST');
        try {
            $this->db->begin();
            $this->dao->fecha = date('Y-m-d H:i:s');
            $this->dao->usuario_id = $this->user_id;
            $this->dao->estado_cocina = 0;
            $this->dao->estado_barra = 0;
            $this->dao->estado = 2;
            $this->dao->user_create = $this->user_id;
            $this->dao->save();
            $query = 'INSERT INTO orden_detalle (`orden_id`, `producto_id`, `nota`, `cantidad`, `cantidad_helper`, `precio_unitario`, `precio_total`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            $last = $this->db->exec("select orden_id from orden order by orden_id desc limit 1");
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $last[0]['orden_id'] . "', '" . $detalle['producto'][$i] . "', '" . $detalle['nota'][$i] . "', '" . $detalle['cantidad'][$i] . "', '" . $detalle['cantidad'][$i] . "', '" . $detalle['unitario'][$i] . "', '" . $detalle['total'][$i] . "')";
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
        $this->dao->load(array('orden_id=?', $id));
        if ($this->dao->estado != 3) {
            $this->dao->copyfrom('POST');
            if ($this->dao->estado == 2) {
                $this->dao->fecha = date('Y-m-d H:i:s');
            }
            try {
                $this->dao->user_update = $this->user_id;
                $this->dao->update();
                if ($this->dao->estado_cocina == 1) {
                    $this->db->exec("
                      UPDATE orden_detalle od
                      INNER JOIN producto p ON p.producto_id = od.producto_id
                      SET od.estado = ?, od.estado_cocina = ?
                      WHERE od.orden_id = ?
                        AND p.destino_id = ?",
                        array(
                            1 => 1,
                            2 => 1,
                            3 => $id,
                            4 => 1
                        )
                    );
                }
                if ($this->dao->estado_barra == 1) {
                    $this->db->exec("
                      UPDATE orden_detalle od
                      INNER JOIN producto p ON p.producto_id = od.producto_id
                      SET od.estado = ?, od.estado_barra = ?
                      WHERE od.orden_id=?
                        AND p.destino_id=?",
                        array(
                            1 => 1,
                            2 => 1,
                            3 => $id,
                            4 => 2
                        )
                    );
                }
                return array('success' => true, 'msg' => 'Registro actualizado.');
            } catch (\PDOException $e) {
                return array('msg' => $e->getMessage());
            }
        } else {
            return array('success' => true, 'msg' => 'No editable');
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('orden_id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function anular($id)
    {
        $this->dao->load(array('orden_id=?', $id));
        try {
            $this->dao->glosa = $_POST['motivo'];
            $this->dao->estado = 3;
            $this->dao->anulado = 1;
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro anulado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function add_detalle()
    {
        try {
            $detail = new \DB\SQL\Mapper($this->db, 'orden_detalle');
            $detail->copyfrom('POST');
            $detail->cantidad_helper = $_POST['cantidad'];
            $detail->date_created = date('Y-m-d H:i:s');
            $detail->date_updated = date('Y-m-d H:i:s');
            $detail->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit_detalle($orden_id, $producto_id)
    {
        try {
            $result = $this->db->query("SELECT * FROM orden_detalle WHERE orden_id='$orden_id' AND producto_id='$producto_id' AND estado=1 LIMIT 1");
            $result->setFetchMode(PDO::FETCH_ASSOC);
            $orden = $result->fetch();
            if ($orden) {
                $cantidad_actual = $orden['cantidad'];
                $cantidad_helper_actual = $orden['cantidad_helper'];
                $cantidad_nueva = $_POST['cantidad'];
                $cantidad_helper_nueva = 0;
                if ($cantidad_actual != $cantidad_nueva) {
                    $cantidad_helper_nueva = $cantidad_nueva - $cantidad_actual;
                } else {
                    $cantidad_helper_nueva = $_POST['cantidad'];
                }
            } else {
                $cantidad_helper_nueva = $_POST['cantidad'];
            }
            $this->db->exec(
                'UPDATE orden_detalle SET cantidad=?, cantidad_helper=?, precio_unitario=?, precio_total=?, nota=?, devolver=?, desechar=?, estado=? WHERE orden_id=? AND producto_id=?',
                array(
                    1 => $_POST['cantidad'],
                    2 => $cantidad_helper_nueva,
                    3 => $_POST['precio_unitario'],
                    4 => $_POST['precio_total'],
                    5 => $_POST['nota'],
                    6 => $_POST['devolver'],
                    7 => $_POST['desechar'],
                    8 => $_POST['estado'],
                    9 => $orden_id,
                    10 => $producto_id
                )
            );
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete_detalle($orden_id, $producto_id)
    {
        try {
            $this->db->exec(
                'DELETE FROM orden_detalle WHERE orden_id=? AND producto_id=?',
                array(
                    1 => $orden_id,
                    2 => $producto_id
                )
            );
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function exportar($fi, $ff)
    {
        return $this->db->exec("CALL sp_reporte_comandas (?,?)", array($fi, $ff));
    }
}
