<?php

namespace App\Model;

use App\Util\TipoDoc;
use DB\SQL\Mapper;
use PDO;

class MovimientoModel extends BaseModel
{

    protected $dao;
    private CajaModel $cajaModel;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'movimiento');
        $this->cajaModel = new CajaModel();
    }

    public function all($tipo_id)
    {
        $format = "numero is null and tipo_id = %s and local_id = '%s'";
        $filter = sprintf($format, $tipo_id, $this->current_local_id);
        return $this->utils->setUp('vw_movimiento', 'fecha', 'DESC', $filter);
    }

    public function allsalidas($tipo_id)
    {
        $format = "numero is null and tipo_id = %s and operacion_id='4c193bb2-c224-11e7-91af-5600005a2745' and local_id = '%s'";
        $filter = sprintf($format, $tipo_id, $this->current_local_id);
        return $this->utils->setUp('vw_movimiento', 'fecha', 'DESC', $filter);
    }

    public function ventas()
    {
        $format = "operacion like 'VENTA%%' and local_id = '%s'";
        $filter = sprintf($format, $this->current_local_id);
        return $this->utils->setUp('vw_movimiento', 'date_created', 'DESC', $filter);
    }

    public function comprobantes()
    {
        $format = "local_id = '%s'";
        $filter = sprintf($format, $this->current_local_id);
        return $this->utils->setUp('vw_comprobante', 'created_at', 'DESC', $filter);
    }

    public function getordenes()
    {
        return $this->db->exec("
        select
          ifnull(o.orden_id, 0) as orden_id,
          m.descripcion         as mesa,
          case ifnull(o.estado, 0)
          when 2
            then 'Atendido'
          when 1
            then 'Atendiendo'
          when 0
            then 'Libre' end    as grupo
        from
          mesa m
          left join orden o on o.mesa_id = m.mesa_id and o.estado <> 3
        where m.estado = 1
        order by o.estado desc, m.descripcion
        ");
    }

    public function getorden($id)
    {
        return $this->db->exec("
          SELECT
            od.orden_id,
            od.producto_id,
            od.cantidad - IFNULL(od.billed_quantity, 0) as cantidad,
            od.cantidad_helper,
            od.precio_unitario,
            od.precio_total,
            od.nota,
            od.devolver,
            od.desechar,
            od.billed,
            od.billed_quantity,
            od.date_created,
            od.date_updated,
            od.estado,
            p.descripcion as producto
          FROM
            orden_detalle od
          INNER JOIN producto p ON p.producto_id = od.producto_id
          WHERE
            od.orden_id = ?
            and ifnull( od.billed, 0 ) = 0", $id);
    }

    public function cerrar_orden($id)
    {
        $orden = new Mapper($this->db, "orden");
        $orden->load(array("orden_id=?", $id));
        try {
            $orden->estado_cocina = 1;
            $orden->estado_barra = 1;
            $orden->estado = 3;
            $orden->user_update = $this->current_user_id;
            $orden->update();
            $this->db->exec("update orden_detalle set estado = 1 where orden_id = ?", $id);
            return array('success' => true, 'msg' => 'Orden finalizada.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function getprecio($id)
    {
        $result = $this->db->exec("
          SELECT
            precio
          FROM
            producto_local
          WHERE
            producto_id=? and local_id=?
        ", array($id, $this->current_local_id));
        return $result;
    }

    public function detalle($id)
    {
        $result = $this->db->exec("
          SELECT
            md.movimiento_id,
            md.producto_id,
            md.cantidad,
            md.precio_unitario,
            CONCAT(ROUND(IFNULL(m.descuento, 0), 2), '%') as descuento,
            ROUND(md.precio_total - (md.precio_total * IFNULL(m.descuento, 0) / 100), 2) as precio_total,
            p.descripcion as producto
          FROM
            movimiento_detalle md
          INNER JOIN movimiento m on md.movimiento_id = m.movimiento_id
          INNER JOIN producto p ON md.producto_id = p.producto_id
          WHERE
            md.movimiento_id = ?
          order by p.descripcion 
        ", array($id));
        return $result;
    }

    public function getall()
    {
        $fi = isset($_POST['fi']) ? strval($_POST['fi']) : date('Y-m-01');
        $ff = isset($_POST['ff']) ? strval($_POST['ff']) : date('Y-m-t');
        $almacen = isset($_POST['almacen']) ? strval($_POST['almacen']) : '0';
        $categoria = isset($_POST['categoria']) ? strval($_POST['categoria']) : '0';
        if ($almacen == '0') {
            $almacen = null;
        }
        if ($categoria == '0') {
            $categoria = null;
        }
        if ($this->app->get('MODE') == 'POS') {
            return $this->db->exec("call sp_reporte_movimientos_pos (?,?,?,?,?)", array($this->current_local_id, $almacen, $categoria, $fi, $ff));
        }
        return $this->db->exec("CALL sp_reporte_movimientos (?,?,?,?)", array($almacen, $categoria, $fi, $ff));
    }

    public function mdetalle($producto)
    {
        $fi = isset($_POST['fi']) ? strval($_POST['fi']) : date('Y-m-01');
        $ff = isset($_POST['ff']) ? strval($_POST['ff']) : date('Y-m-t');
        $almacen = isset($_POST['almacen']) ? strval($_POST['almacen']) : '0';
        if ($almacen == '' || $almacen == '0') {
            $almacen = null;
        }
        return $this->db->exec("CALL sp_kardex(?,?,?,?)", array($almacen, $producto, $fi, $ff));
    }

    public function get_serie($tipo)
    {
        $data = $this->db->exec("
          select
            serie
          from local_comprobante
          where tipo_comprobante_id = ?
          and local_id = ?", array($tipo, $this->current_local_id)
        );
        return $data[0]['serie'];
    }

    public function next_numero($tipo)
    {
        $serie = $this->get_serie($tipo);
        $result = $this->db->exec("
          select
            max(cast(numero as int)) as numero
          from movimiento 
          where serie=?", $serie);
        $numero = $result[0]['numero'];
        if ($numero) {
            $numero += 1;
        } else {
            $numero = 1;
        }
        return $numero;
    }

    public function add($tipo_id)
    {
        try {
            $this->db->begin();
            $this->dao->copyfrom('POST');
            if ($this->dao->operacion_id == '0ec9c6ae-c04f-11e7-91af-5600005a2745' || $this->dao->operacion_id == '37b1e294-c224-11e7-91af-5600005a2745' || $this->dao->operacion_id == '5c02c90b-74fc-11eb-a834-005056409407') {
                if ($this->dao->operacion_id == '0ec9c6ae-c04f-11e7-91af-5600005a2745') {
                    $serie = $this->get_serie(TipoDoc::BOLETA);
                    $numero = $this->next_numero(TipoDoc::BOLETA);
                } else if ($this->dao->operacion_id == '37b1e294-c224-11e7-91af-5600005a2745') {
                    $serie = $this->get_serie(TipoDoc::FACTURA);
                    $numero = $this->next_numero(TipoDoc::FACTURA);
                } else if ($this->dao->operacion_id == '5c02c90b-74fc-11eb-a834-005056409407') {
                    $serie = $this->get_serie(TipoDoc::NOTA_PEDIDO);
                    $numero = $this->next_numero(TipoDoc::NOTA_PEDIDO);
                }
                $this->dao->fecha = date('Y-m-d');
                $this->dao->almacen_id = $this->current_almacen_id;
            } else {
                $serie = null;
                $numero = null;
            }
            $key = $this->db->exec("SELECT UUID() AS uuid");
            $uuid = $key[0]['uuid'];
            $this->dao->movimiento_id = $uuid;
            $this->dao->tipo_id = $tipo_id;
            $this->dao->proveedor_id = empty($this->dao->proveedor_id) ? null : $this->dao->proveedor_id;
            $this->dao->serie = $serie;
            $this->dao->numero = $numero;
            $this->dao->estado = 1;
            $this->dao->created_by = $this->current_user_id;
            $this->dao->save();
            $query = 'INSERT INTO movimiento_detalle (`movimiento_id`, `producto_id`, `cantidad`, `precio_unitario`, `descuento`, `precio_total`) VALUES ';
            $query_parts = array();
            $detalle = $_POST['detalle'];
            for ($i = 0; $i < count($detalle['producto']); $i++) {
                $query_parts[] = "('" . $uuid . "', '" . $detalle['producto'][$i] . "', '" . $detalle['cantidad'][$i] . "', '" . $detalle['unitario'][$i] . "', '" . $detalle['descuento'][$i] . "', '" . $detalle['total'][$i] . "')";
                if ($this->dao->orden_id > 0) {
                    $orderDetail = new Mapper($this->db, 'orden_detalle');
                    $orderDetail->load(['orden_id = ? and producto_id = ?', $this->dao->orden_id, $detalle['producto'][$i]]);
                    if (($orderDetail->cantidad - $orderDetail->billed_quantity) == $detalle['cantidad'][$i]) {
                        $orderDetail->billed = 1;
                    }
                    $orderDetail->billed_quantity += $detalle['cantidad'][$i];
                    $orderDetail->save();
                }
            }
            $this->db->exec($query .= implode(',', $query_parts));
            $pending = false;
            if ($this->dao->orden_id > 0) {
                $counter = $this->db->exec("select count(producto_id) as counter from orden_detalle where orden_id = ? and ifnull( billed, 0 ) = 0", $this->dao->orden_id);
                if ($counter && $counter[0]['counter'] > 0) {
                    $pending = true;
                }
            }
            $this->db->commit();
            $this->cajaModel->update_caja();
            $venta = array();
            $cabecera = $this->ticket_cabecera($uuid);
            $detalle = $this->ticket_detalle($uuid);
            $venta["cabecera"] = $cabecera;
            $venta["detalle"] = $detalle;
            return array('success' => true, 'msg' => 'Registro guardado.', 'venta' => $venta, 'pending' => $pending);
        } catch (\PDOException $e) {
            $this->db->rollback();
            return array('msg' => $e->getMessage(), 'data' => $this->db->log());
        }
    }

    function saldo($producto, $almacen)
    {
        $result = $this->db->exec("
            SELECT
                producto_id,
                SUM( CASE tipo_id WHEN 1 THEN cantidad WHEN 2 THEN cantidad * - 1 END ) AS stock 
            FROM
                vw_movimiento_detalle 
            WHERE
                estado = 1 
                AND almacen_id = ?
                AND producto_id = ?
            GROUP BY
                producto_id
        ", [$almacen, $producto]);
        return $result[0];
    }

    public function edit($id)
    {
        $this->dao->load(array('movimiento_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'UPDATE movimiento SET fecha=?, operacion_id=?, almacen_id=?, glosa=?, date_updated=? WHERE movimiento_id=?',
                array(
                    1 => $_POST['fecha'],
                    2 => $_POST['operacion_id'],
                    3 => $_POST['almacen_id'],
                    4 => $_POST['glosa'],
                    5 => date('Y-m-d H:i:s'),
                    6 => $id
                )
            );
            $this->db->exec('DELETE from movimiento_detalle WHERE movimiento_id = ?', $id);
            $query = 'INSERT INTO movimiento_detalle (`movimiento_id`, `producto_id`, `cantidad`) VALUES ';
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
        $this->dao->load(array('movimiento_id=?', $id));
        try {
            $this->db->exec('DELETE from movimiento_detalle WHERE movimiento_id = ?', [$id]);
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function anular($id)
    {
        $this->dao->load(array('movimiento_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec(
                'UPDATE movimiento SET estado=?, glosa=?, updated_by=? WHERE movimiento_id=?',
                array(
                    1 => 2,
                    2 => $_POST['motivo'],
                    3 => $this->current_user_id,
                    4 => $id
                )
            );
            if ($this->dao->orden_id > 0) {
                $this->db->exec('UPDATE orden SET anulado = ? WHERE orden_id = ?', [1, $this->dao->orden_id]);
            }
            $this->db->commit();
            $this->cajaModel->update_caja();
            return array('success' => true, 'msg' => 'Registro anulado.');
        } catch (\PDOException $e) {
            $this->db->rollback();
            return array('msg' => $e->getMessage());
        }
    }

    public function salidaanular($id)
    {
        $this->dao->load(array('movimiento_id=?', $id));
        try {
            $this->db->begin();
            $this->db->exec('UPDATE movimiento SET estado=? WHERE movimiento_id=?', [2, $id]);
            $this->db->commit();
            $this->cajaModel->update_caja();
            return array('success' => true, 'msg' => 'Registro anulado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function ticket_cabecera($id)
    {
        $result = $this->db->exec("
          SELECT
            m.movimiento_id,
            m.fecha,
            e.razon_social AS empresa,
            e.ruc AS ruc_empresa,
            e.domicilio_fiscal AS direccion_empresa,
            l.direccion AS direccion_local,
            e.telefono,
            m.operacion_id,
            m.serie,
            m.numero,
            m.descuento,
            m.glosa,
            '' AS aut_sunat,
            '' AS impresora_marca,
            '' AS impresora_modelo,
            '' AS impresora_serie,
            c.ruc AS ruc_cliente,
            c.razon_social AS cliente,
            c.domicilio_fiscal AS direccion_cliente,
            c.email as email_cliente,
            d.p_venta AS total 
        FROM
            movimiento m
            INNER JOIN vw_descuento d ON d.movimiento_id = m.movimiento_id
            INNER JOIN almacen a ON a.almacen_id = m.almacen_id
            INNER JOIN `local` l ON l.local_id = a.local_id
            INNER JOIN empresa e ON e.empresa_id = l.empresa_id
            INNER JOIN cliente c ON c.ruc = m.cliente_id 
        WHERE
            m.movimiento_id = ?", array($id));
        return $result[0];
    }

    public function ticket_detalle($id)
    {
        $result = $this->db->query("
      SELECT
        p.descripcion as producto,
        md.cantidad,
        md.precio_unitario as unitario,
        md.precio_total as precio
      FROM movimiento_detalle md
      INNER JOIN producto p ON p.producto_id = md.producto_id
      WHERE md.movimiento_id='$id'
    ");
        $result->setFetchMode(PDO::FETCH_ASSOC);
        return $result->fetchall();
    }

    public function get_saldo($almacen, $producto, $fecha)
    {
        return $this->db->exec("
             CALL sp_get_saldo (?,?,?)", array($almacen, $producto, $fecha));
    }

    public function articulos_kardex($almacen, $producto, $fi, $ff, $saldo)
    {
        return $this->db->exec("
             CALL sp_reporte_kardex (?,?,?,?,?)", array($almacen, $producto, $fi, $ff, $saldo));
    }

    public function reporte_kardex($almacen, $categoria, $fi, $ff)
    {
        if ($almacen == '0') {
            $almacen = null;
        }
        if ($categoria == '0') {
            $categoria = null;
        }
        if ($this->app->get('MODE') == 'POS') {
            return $this->db->exec("call sp_reporte_movimientos_pos (?,?,?,?,?)", array($this->current_local_id, $almacen, $categoria, $fi, $ff));
        }
        return $this->db->exec("CALL sp_reporte_movimientos (?,?,?,?)", array($almacen, $categoria, $fi, $ff));
    }

    public function guardar_comprobante()
    {
        $comprobante = new Mapper($this->db, 'comprobante');
        $comprobante->copyFrom($_POST['comprobante']);
        try {
            $comprobante->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function get_one_venta($id)
    {
        $venta = array();
        $cabecera = $this->ticket_cabecera($id);
        $detalle = $this->ticket_detalle($id);
        if ($cabecera && $detalle) {
            $venta["cabecera"] = $cabecera;
            $venta["detalle"] = $detalle;
            return array('success' => true, 'msg' => '', 'venta' => $venta);
        } else {
            return array('success' => false, 'msg' => 'No se encontro la venta', 'venta' => $venta);
        }
    }

    public function update_venta()
    {
        $this->db->exec('CALL sp_fill_comprobantes');
        $comprobante = new \DB\SQL\Mapper($this->db, 'comprobante');
        $data = json_decode($_POST['data'], true);
        $comprobante->load(array('movimiento_id=?', $data['movimiento_id']));
        $comprobante->envio_sunat = $data['code'];
        $comprobante->mensaje_sunat = $data['message'];
        $comprobante->updated_at = date('Y-m-d H:i:s');
        try {
            $comprobante->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function makeInvoice($id): array
    {
        try {
            $result = $this->db->exec('CALL sp_make_invoice(?,?)', [$id, $this->current_local_id]);
            return ["success" => true, "data" => $result, "msg" => 'Comprobante registrado'];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }
}
