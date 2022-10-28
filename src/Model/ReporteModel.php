<?php

namespace App\Model;

class ReporteModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function all()
    {
        return $this->utils->setUp('vw_producto', 'producto_id');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM producto WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM producto ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('producto_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function listafull()
    {
        return $this->db->exec("SELECT * FROM producto WHERE tipo_id=2 AND estado=1 ORDER BY descripcion");
    }

    public function articulos($fi, $ff)
    {
        $result = $this->db->exec("
      SELECT
        p.codigo,
        p.descripcion,
        COUNT(IF(m.numero>0, md.movimiento_id, NULL)) AS movimientos,
        SUM(IF(m.numero>0, md.cantidad, 0)) AS cantidad,
        IFNULL(md.precio_unitario, p.precio) AS precio_unitario, 
        SUM(IF(m.numero>0, md.precio_total, 0)) AS precio_venta
      FROM producto p
      LEFT JOIN movimiento_detalle md ON md.producto_id=p.producto_id
      LEFT JOIN movimiento m ON m.movimiento_id=md.movimiento_id AND m.estado=1 AND (fecha BETWEEN '$fi' AND '$ff') AND numero > 0
        WHERE p.tipo_id = 2
      GROUP BY p.descripcion
      ORDER BY cantidad DESC
    ");
        return $result;
    }

    public function articulos_detalle($fi, $ff)
    {
        $result = $this->db->exec("
          SELECT 
            m.fecha AS fecha,
            m.serie AS serie,
            m.numero AS numero,
            c.ruc,
            c.razon_social,
            p.codigo AS codigo,
            p.descripcion AS producto,
            ca.descripcion AS categoria,
            COUNT(IF(m.numero > 0, md.movimiento_id, NULL)) AS movimientos,
            SUM(IF(m.numero > 0, md.cantidad, 0)) AS cantidad,
            IFNULL(md.precio_unitario, p.precio) AS precio_unitario,
            SUM(IF(m.numero > 0, md.precio_total, 0)) AS precio_venta
        FROM
            producto p
                LEFT JOIN
            movimiento_detalle md ON md.producto_id = p.producto_id
                LEFT JOIN
            movimiento m ON m.movimiento_id = md.movimiento_id
                AND m.estado = 1
                AND (fecha BETWEEN '$fi' AND '$ff')
                AND numero > 0
          LEFT JOIN
            cliente c ON c.ruc = m.cliente_id
            LEFT JOIN
            categoria ca ON ca.categoria_id = p.categoria_id
        WHERE
            p.tipo_id = 2 and
            m.fecha IS not NULL
        GROUP BY p.descripcion
        ORDER BY m.fecha DESC
            ");
        return $result;
    }

    public function articulos_dia($fi)
    {
        $result = $this->db->exec("
      SELECT 
        p.codigo, 
        p.descripcion, 
        p.categoria_id, 
        p.destino_id, 
        m.fecha , 
        COUNT(IF(m.numero>0, md.movimiento_id, NULL)) AS movimientos, 
        SUM(IF(m.numero>0, md.cantidad, 0)) AS cantidad, IFNULL(md.precio_unitario, p.precio) AS precio_unitario, 
        SUM(IF(m.numero>0, md.precio_total, 0)) AS precio_venta 
        FROM producto p LEFT JOIN movimiento_detalle md ON md.producto_id=p.producto_id 
        LEFT JOIN movimiento m ON m.movimiento_id=md.movimiento_id AND m.estado=1 AND (m.fecha ='$fi') 
        WHERE p.tipo_id = 2 
        GROUP BY p.descripcion 
        ORDER BY cantidad DESC
    ");
        return $result;
    }

    public function notaventa($fi, $ff)
    {
        $result = $this->db->exec("call sp_reporte_notaventa(?,?,?)", array($fi, $ff, $this->current_local_id));
        return $result;
    }

    public function contable($fi, $ff)
    {
        $result = $this->db->exec("call sp_reporte_contable(?,?,?)", array($fi, $ff, $this->current_local_id));
        return $result;
    }

    public function aeropuerto($fecha)
    {
        return $this->db->exec("call sp_reporte_app(?,?)", [$this->current_local_id, $fecha]);
    }

    public function anulados($fecha_desde, $fecha_hasta)
    {
        return $this->db->exec("call sp_reporte_anulados(?, ?)", array($fecha_desde, $fecha_hasta));
    }

    public function notasCredito($fecha_desde, $fecha_hasta)
    {
        return $this->db->exec("call sp_reporte_notasCredito(?, ?)", array($fecha_desde, $fecha_hasta));
    }

    public function asistencia_diaria($fecha)
    {
        return $this->db->exec("call sp_reporte_asistencia_diaria(?)", array($fecha));
    }

    public function asistencia_mensual($empleado, $desde, $hasta)
    {
        return $this->db->exec("call sp_reporte_asistencia_mensual(?,?,?)", array($empleado, $desde, $hasta));
    }

    public function asistencia_resumen($empleado, $desde, $hasta)
    {
        return $this->db->exec("call sp_reporte_asistencia_resumen(?,?,?)", array($empleado, $desde, $hasta));
    }
}
