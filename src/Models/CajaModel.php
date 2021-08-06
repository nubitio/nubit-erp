<?php

use DB\SQL\Mapper;

class CajaModel extends BaseModel
{
    private $caja;
    private $detail;

    public function __construct()
    {
        parent::__construct();
        $this->caja = new Mapper($this->db, 'caja');
        $this->detail = new Mapper($this->db, 'caja_detalle');
    }

    public function all()
    {
        $format = "local_id = '%s'";
        $filter = sprintf($format, $this->current_local_id);
        return $this->utils->setUp('vw_caja', 'apertura, local', 'desc, asc', $filter);
    }

    public function check()
    {
        $result = $this->db->exec("
          SELECT
            c.caja_id,
            date(c.apertura) as fecha,
            c.usuario_id,
            u.username as cajera
          FROM
            caja c
          INNER JOIN usuario u ON u.usuario_id = c.usuario_id
          WHERE
            c.estado=1
        ");
        return $result;
    }

    public function detalle($id)
    {
        $result = $this->db->query("
          SELECT
            *
          FROM
            caja_detalle
          WHERE
            caja_id = '$id' 
          ORDER BY
            tipo_id,
            descripcion
        ");
        $result->setFetchMode(PDO::FETCH_ASSOC);
        $rs['rows'] = $result->fetchall();
        $total_ingresos = 0;
        $total_egresos = 0;
        $total_efectivo = 0;
        for ($i = 0; $i < count($rs['rows']); $i++) {
            if ($rs['rows'][$i]['tipo_id'] == 1) {
                $total_ingresos += $rs['rows'][$i]['monto'];
            } else {
                $total_egresos += $rs['rows'][$i]['monto'];
            }
        }
        $total_efectivo = $total_ingresos - $total_egresos;
        $footer = array(
            array(
                'referencia' => 'Total Ingresos',
                'monto' => number_format($total_ingresos, 2)
            ),
            array(
                'referencia' => 'Total Egresos',
                'monto' => number_format($total_egresos, 2)
            ),
            array(
                'referencia' => 'Total Efectivo',
                'monto' => number_format($total_efectivo, 2)
            )
        );
        $rs['footer'] = $footer;
        return $rs;
    }

    public function next_uid($caja_id)
    {
        $result = $this->db->query("SELECT max(uid) as uid FROM caja_detalle WHERE caja_id='$caja_id'");
        $result->setFetchMode(PDO::FETCH_ASSOC);
        $result = $result->fetch();
        $uid = $result['uid'];
        if ($uid) {
            $uid += 1;
        } else {
            $uid = 1;
        }
        return $uid;
    }

    public function add()
    {
        try {
            $this->db->begin();
            $datetime = date("Y-m-d H:i:s");
            $key = $this->db->exec("SELECT UUID() AS uuid");
            $uuid = $key[0]['uuid'];
            $this->caja->caja_id = $uuid;
            $this->caja->local_id = $this->current_local_id;
            $this->caja->apertura = $datetime;
            $this->caja->usuario_id = $_POST['usuario_id'];
            $this->caja->estado = 1;
            $this->caja->user_create = $this->current_user_id;
            $this->caja->save();
            $last = $uuid;
            $uid = $this->next_uid($last);
            $this->detail->uid = $uid;
            $this->detail->caja_id = $last;
            $this->detail->tipo_id = 1;
            $this->detail->descripcion = 'FONDO DE CAJA';
            $this->detail->monto = $_POST['saldo_inicial'];
            $this->detail->save();
            $uid = $this->next_uid($last);
            $this->detail->reset();
            $this->detail->uid = $uid;
            $this->detail->caja_id = $last;
            $this->detail->tipo_id = 1;
            $this->detail->descripcion = 'VENTA EFECTIVO';
            $this->detail->monto = 0;
            $this->detail->save();
            $this->detail->reset();
            $uid = $this->next_uid($last);
            $this->detail->uid = $uid;
            $this->detail->caja_id = $last;
            $this->detail->tipo_id = 1;
            $this->detail->descripcion = 'VENTA TARJETA';
            $this->detail->monto = 0;
            $this->detail->save();
            $this->db->commit();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function cerrar()
    {
        try {
            $datetime = date('Y-m-d H:i:s');
            $this->db->exec(
                'UPDATE caja SET estado=?, cierre=? WHERE caja_id=?',
                array(
                    1 => 2,
                    2 => $datetime,
                    3 => $_POST['caja_id']
                )
            );
            return array('success' => true, 'msg' => 'Cierre realizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function flujo()
    {
        try {
            $result = $this->db->exec('SELECT caja_id FROM caja ORDER BY apertura DESC LIMIT 1');
            $last = $result[0]['caja_id'];
            $uid = $this->next_uid($last);
            $this->detail->copyfrom('POST');
            $this->detail->uid = $uid;
            $this->detail->caja_id = $last;
            $this->detail->user_create = $this->current_user_id;
            $this->detail->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($uid, $caja_id)
    {
        try {
            $this->detail->load(array('uid = ? and caja_id = ?', $uid, $caja_id));
            $this->detail->copyfrom('POST');
            $this->detail->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($uid, $caja_id)
    {
        try {
            $this->detail->load(array('uid = ? and caja_id = ?', $uid, $caja_id));
            $this->detail->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function cabecera($id)
    {
        $result = $this->db->query("
          SELECT
            u.username,
            c.apertura,
            c.cierre,
            SUM( CASE WHEN cd.descripcion = 'VENTA EFECTIVO' THEN cd.monto ELSE 0 END ) AS contado,
            SUM( CASE WHEN cd.descripcion = 'VENTA TARJETA' THEN cd.monto ELSE 0 END ) AS credito,
            SUM( CASE WHEN cd.descripcion LIKE 'VENTA%' THEN cd.monto ELSE 0 END ) AS total
          FROM
            caja c
            INNER JOIN caja_detalle cd ON cd.caja_id = c.caja_id
            INNER JOIN usuario u ON u.usuario_id = c.usuario_id
          WHERE c.caja_id = '$id'
        ");
        $result->setFetchMode(PDO::FETCH_ASSOC);
        return $result->fetch();
    }

    public function get_caja_info($apertura, $cierre)
    {
        return $this->db->exec('CALL sp_caja_info (?,?,?)', array(1 => $this->current_local_id, 2 => $apertura, 3 => $cierre));
    }

    public function detalle_reporte($id)
    {
        $result = $this->db->query("
          SELECT
            *
          FROM
            caja_detalle
          WHERE
            caja_id = '$id' 
          ORDER BY
            tipo_id,
            descripcion
        ");
        $result->setFetchMode(PDO::FETCH_ASSOC);
        return $result->fetchall();
    }
}
