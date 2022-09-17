<?php

namespace App\Model;

use DB\SQL\Mapper;
use PDO;

class CajaModel extends BaseModel
{
    private Mapper $caja;
    private Mapper $detail;

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

    /**
     * @throws \Exception
     */
    public function getCurrentActiveCashBox(): array
    {
        $data = $this->db->exec("
          SELECT
            c.caja_id,
            date(c.apertura) as fecha,
            c.apertura,
            c.usuario_id,
            u.username as cajera
          FROM
            caja c
          INNER JOIN usuario u ON u.usuario_id = c.usuario_id
          WHERE
            c.estado = 1
            AND c.local_id = ?
            AND c.usuario_id = ?
        ", [$this->current_local_id, $this->current_user_id]);
        if ($data) {
            return $data[0];
        } else {
            throw new \Exception('No se encontró caja activa');
        }
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
            uid
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
                'monto'      => number_format($total_ingresos, 2)
            ),
            array(
                'referencia' => 'Total Egresos',
                'monto'      => number_format($total_egresos, 2)
            ),
            array(
                'referencia' => 'Total Efectivo',
                'monto'      => number_format($total_efectivo, 2)
            )
        );
        $rs['footer'] = $footer;
        return $rs;
    }

    public function next_uid($caja_id): int
    {
        $result = $this->db->exec("SELECT max(uid) as uid FROM caja_detalle WHERE caja_id = ?", [$caja_id]);
        $uid = $result[0]['uid'];
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
            $this->caja->usuario_id = $this->current_user_id;
            $this->caja->estado = 1;
            $this->caja->save();
            $last = $uuid;
            $uid = $this->next_uid($last);
            $this->detail->uid = $uid;
            $this->detail->caja_id = $last;
            $this->detail->tipo_id = 1;
            $this->detail->descripcion = 'FONDO DE CAJA';
            $this->detail->monto = $_POST['saldo_inicial'];
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
            $result = $this->getCurrentActiveCashBox();
            $cashBoxId = $result['caja_id'];
            $this->detail->copyfrom('POST');
            $this->detail->uid = $this->next_uid($cashBoxId);
            $this->detail->caja_id = $cashBoxId;
            $this->detail->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\Exception $e) {
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

    public function get_caja_info($apertura, $cierre)
    {
        return $this->db->exec('CALL sp_caja_info (?,?,?)', array(1 => $this->current_local_id, 2 => $apertura, 3 => $cierre));
    }

    public function getOneById($id)
    {
        $data = $this->db->exec('select * from vw_caja where caja_id = ?', $id);
        return $data[0];
    }

    public function getDetailById($id)
    {
        return $this->db->exec('select * from caja_detalle where caja_id = ?', $id);
    }

    /**
     * @throws \Exception
     */
    public function update_caja()
    {
        try {
            $this->db->begin();
            $caja = $this->getCurrentActiveCashBox();
            if ($caja) {
                $caja_id = $caja['caja_id'];
                $startDate = $caja['apertura'];
                $endDate = date('Y-m-d H:i:s');
                $totals = $this->db->exec("
                    SELECT
                        pt.id,
                        pt.`name`,
                        SUM( ROUND( precio_total - ( precio_total * IFNULL( m.descuento, 0 ) / 100 ), 2 ) ) as total
                    FROM
                        movimiento_detalle md
                        INNER JOIN movimiento m ON m.movimiento_id = md.movimiento_id
                        INNER JOIN payment_type pt ON pt.id = m.tipo_pago 
                        AND m.estado = 1 
                        AND m.numero > 0 
                    WHERE
                        m.created_by = ?
                        AND (m.date_created BETWEEN ? AND ?) 
                    GROUP BY
                        pt.id", [$this->current_user_id, $startDate, $endDate]);

                $detail = new Mapper($this->db, 'caja_detalle');
                foreach ($totals as $total) {
                    $detail->load(['caja_id = ? and descripcion = ?', $caja_id, $total['name']]);
                    if ($detail->dry()) {
                        $detail->uid = $this->next_uid($caja_id);
                    }
                    $detail->tipo_id = 1;
                    $detail->caja_id = $caja_id;
                    $detail->descripcion = $total['name'];
                    $detail->monto = $total['total'];

                    $detail->save();
                }
                $this->db->commit();
            }
        } catch (\PDOException $e) {
            $this->db->rollback();
            throw new \Exception($e->getMessage(), 0, $e);
        }
    }
}
