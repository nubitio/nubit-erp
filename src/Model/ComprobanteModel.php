<?php

namespace App\Model;

class ComprobanteModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'tipo_comprobante');
    }

    public function all()
    {
        return $this->utils->setUp('tipo_comprobante', 'codigo', 'ASC');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM tipo_comprobante WHERE estado=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM tipo_comprobante ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('tipo_comprobante_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function add()
    {
        $this->dao->copyFrom('POST');
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->dao->load(array('codigo=?', $id));
        $this->dao->copyFrom('POST');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('codigo=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function exportar()
    {
        return $this->db->exec("
            SELECT
                *
            FROM tipo_comprobante
        ");
    }

    public function ticketVoidedUpdate($ticket ,$receiptId)
    {
        $this->db->exec("UPDATE comprobante SET ticket_baja = ? WHERE movimiento_id = ?", array($ticket, $receiptId));
    }
}
