<?php

namespace App\Model;

class BotModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return string
     */
    public function getUUID()
    {
        $data = $this->db->exec("select UUID() as id");
        return $data[0]['id'];
    }

    public function fillPending()
    {
        try {
            $data = $this->db->exec("CALL sp_fill_comprobantes");
            return ["success" => true, "msg" => "{$data[0]['counter']} Registros insertados", "data" => null];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => null];
        }
    }

    public function getPendingInvoices()
    {
        try {
            $data = $this->db->exec("select * from comprobante where envio_sunat is null");
            return ["success" => true, "msg" => count($data) . " Registros encontrados", "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => []];
        }
    }

    public function getWrongInvoices()
    {
        try {
            $data = $this->db->exec("select * from comprobante where envio_sunat > 0 and envio_sunat < 2000");
            return ["success" => true, "msg" => count($data) . " Registros encontrados", "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => []];
        }
    }

    public function getOne($id)
    {
        $data = [];
        $header = $this->saleHeader($id);
        $detail = $this->saleDetail($id);
        if ($header && count($detail) > 0) {
            $data["header"] = $header;
            $data["detail"] = $detail;
            return ["success" => true, "msg" => '', 'data' => $data];
        } else {
            return ["success" => false, "msg" => 'No se encontro el registro', 'data' => null];
        }
    }

    public function getOneNote($id): array
    {
        $note = $this->db->exec("select * from nota where nota_id = '$id'");
        if ($note) {
            $sale = $this->getOne($note[0]['movimiento_id']);
            if ($sale["success"]) {
                $sale["data"]["note"] = $note[0];
            } else {
                return $sale;
            }
            return ["success" => true, "msg" => '', 'data' => $sale['data']];
        } else {
            return ["success" => false, "msg" => 'No se encontro el registro', 'data' => null];
        }
    }

    public function updateSaleState($data)
    {
        $comprobante = new \DB\SQL\Mapper($this->db, 'comprobante');
        $comprobante->load(array('movimiento_id=?', $data['id']));
        $comprobante->digest = $data['hash'] ?? $comprobante->digest;
        $comprobante->envio_sunat = $data['code'];
        $comprobante->mensaje_sunat = $data['msg'];
        $comprobante->updated_at = date('Y-m-d H:i:s');
        try {
            $comprobante->update();
            return ["success" => true, "msg" => 'Registro actualizado.', "data" => null];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => null];
        }
    }

    private function saleHeader($id)
    {
        $result = $this->db->exec("CALL sp_sale_header(?)", [$id]);
        if ($result) {
            return $result[0];
        } else {
            return null;
        }
    }

    private function saleDetail($id)
    {
        return $this->db->exec("CALL sp_sale_detail(?)", [$id]);
    }

    public function getVoided()
    {
        return $this->db->exec("CALL sp_select_pending_voided");
    }

    public function getRejected()
    {
        return $this->db->exec("select m.* from movimiento m inner join nota n on n.movimiento_id = m.movimiento_id where n.envio_sunat = ?", 2119);
    }

    public function getPendingNotes()
    {
        try {
            $data = $this->db->exec("select * from nota where envio_sunat is null");
            return ["success" => true, "msg" => count($data) . " Registros encontrados", "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => []];
        }
    }

    public function getWrongNotes()
    {
        try {
            $data = $this->db->exec("select * from nota where envio_sunat > 0 and envio_sunat < 2000");
            return ["success" => true, "msg" => count($data) . " Registros encontrados", "data" => $data];
        } catch (Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => []];
        }
    }

    public function updateNoteState($data)
    {
        $note = new \DB\SQL\Mapper($this->db, 'nota');
        $note->load(array('nota_id=?', $data['id']));
        $note->digest = $data['hash'];
        $note->envio_sunat = $data['code'];
        $note->mensaje_sunat = $data['msg'];
        $note->updated_at = date('Y-m-d H:i:s');
        try {
            $note->update();
            return ["success" => true, "msg" => 'Registro actualizado.', "data" => null];
        } catch (\Exception $e) {
            return ["success" => false, "msg" => $e->getMessage(), "data" => null];
        }
    }
}