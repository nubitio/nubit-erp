<?php

namespace App\Model;

class SummaryModel extends BaseModel
{
    /**
     * @param string $date
     * @return int
     * @throws Exception
     */
    public function getNextNumber($date)
    {
        $result = $this->db->exec("select ifnull(max(right(correlative, 1)), 0) + 1 as correlative from summary where date = ?", [$date]);
        if ($result) {
            return $result[0]['correlative'];
        } else {
            throw new Exception("Unable to get the next number");
        }
    }

    function getPending()
    {
        try {
            $data = $this->db->exec("
                SELECT
                    fecha,
                    count( numero ) AS comprobantes 
                FROM
                    vw_comprobante 
                WHERE
                    ( envio_sunat = 1079 OR envio_sunat IS NULL ) 
	                AND local_id = ? 
	                AND serie LIKE 'B0%' 
                GROUP BY
                    fecha 
                ORDER BY
                    fecha
            ", [$this->current_local_id]);
            return ["success" => true, "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    function  getVoided()
    {
        try {
            $data = $this->db->exec("
                SELECT
                    total,
                    subtotal,
                    gravada,
                    rc,
                    
                    ");
        } catch (\Exception $e) {
            return 0;
        }
    }


    function getPendingNotes()
    {
        try {
            $data = $this->db->exec("
                SELECT
                    fecha,
                    count( numero ) AS comprobantes 
                FROM
                    vw_nota 
                WHERE
                    ( envio_sunat = 1079 OR envio_sunat IS NULL ) 
	                AND local_id = ?  
                GROUP BY
                    fecha 
                ORDER BY
                    fecha
            ", [$this->current_local_id]);
            return ["success" => true, "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    function getPendingDetail($date)
    {
        try {
            $data = $this->db->exec("
                SELECT
                    * 
                FROM
                    vw_comprobante 
                WHERE
                    ( envio_sunat = 1079 OR envio_sunat IS NULL ) 
	                AND local_id = ? 
                    AND fecha = ?
	                AND serie LIKE 'B0%' 
                ORDER BY
                    fecha
            ", [$this->current_local_id, $date]);
            return ["success" => true, "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    function getComprobanteVoided($id)
    {
        try {
            $data = $this->db->exec("
                SELECT
                    * 
                FROM
                    comprobante 
                WHERE
                    serie LIKE 'B0%'
                AND
                    movimiento_id = ?
            ", $id);
            return ["success" => true, "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    function getPendingNotesDetail($date)
    {
        try {
            $data = $this->db->exec("
                SELECT
                    * 
                FROM
                    vw_nota 
                WHERE
                    ( envio_sunat = 1079 OR envio_sunat IS NULL ) 
	                AND local_id = ? 
                    AND fecha = ?
                ORDER BY
                    fecha
            ", [$this->current_local_id, $date]);
            return ["success" => true, "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    function insertSummary($result)
    {
        $summary = new \DB\SQL\Mapper($this->db, 'summary');
        $summary->date = date('Y-m-d');
        $summary->ticket = $result['ticket'];
        $summary->correlative = $result['id'];
        $summary->hash = $result['hash'];
        $summary->code = $result['code'];
        $summary->msg = $result['msg'];
        $summary->created_at = date('Y-m-d H:i:s');
        try {
            $summary->save();
            return ["success" => true, "msg" => 'Registro insertado.', "data" => null];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => null];
        }
    }

    function updatePending($data, $result)
    {
        try {
            $this->db->begin();
            foreach ($data['data'] as $item) {
                $this->db->exec("
                    UPDATE comprobante
                    SET envio_sunat = ?, 
                        digest = ?, 
                        mensaje_sunat = ?
                    WHERE comprobante_id = ?",
                    [$result['code'], $result['hash'], $result['msg'], $item['comprobante_id']]);
            }
            $this->db->commit();
            return ["success" => true, "data" => null];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    function updatePendingNotes($data, $result)
    {
        try {
            $this->db->begin();
            foreach ($data['data'] as $item) {
                $this->db->exec("
                    UPDATE nota
                    SET envio_sunat = ?, 
                        digest = ?, 
                        mensaje_sunat = ?
                    WHERE nota_id = ?",
                    [$result['code'], $result['hash'], $result['msg'], $item['nota_id']]);
            }
            $this->db->commit();
            return ["success" => true, "data" => null];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }
}