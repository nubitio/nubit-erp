<?php

namespace App\Model;

use DB\SQL\Mapper;

class TransferModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'transfer');
    }

    public function get()
    {
        return $this->utils->setUp('vw_transfer', 'created_at', 'desc');
    }

    public function insert()
    {
        $this->dao->copyfrom('POST');
        $uuid = $this->db->exec("select UuidToBin(UUID()) as uuid");
        $id = $uuid[0]['uuid'];
        $this->dao->transfer_id = $id;
        $this->dao->created_by = $this->current_user_id;
        $this->dao->created_at = date('Y-m-d H:i:s');
        try {
            //salida
            $out = new Mapper($this->db, 'movimiento');
            $outId = $this->db->exec("select UUID() as id");
            $out->movimiento_id = $outId[0]['id'];
            $out->tipo_id = 2;
            $out->fecha = $this->dao->fecha;
            $out->operacion_id = '8f1504dc-5a91-11ea-8a8a-5600005a2745';
            $out->almacen_id = $this->dao->almacen_origen;
            $out->estado = 1;
            $out->save();
            $this->dao->salida_id = $outId[0]['id'];
            for ($i = 0; $i < count($_POST['detalle']['producto']); $i++) {
                $item = new Mapper($this->db, 'movimiento_detalle');
                $item->movimiento_id = $outId[0]['id'];
                $item->producto_id = $_POST['detalle']['producto'][$i];
                $item->cantidad = $_POST['detalle']['cantidad'][$i];
                $item->save();
            }
            //ingreso
            $in = new Mapper($this->db, 'movimiento');
            $inId = $this->db->exec("select UUID() as id");
            $in->movimiento_id = $inId[0]['id'];
            $in->tipo_id = 1;
            $in->fecha = $this->dao->fecha;
            $in->operacion_id = '8774897a-5a91-11ea-8a8a-5600005a2745';
            $in->almacen_id = $this->dao->almacen_origen;
            $in->estado = 1;
            $in->save();
            $this->dao->ingreso_id = $inId[0]['id'];
            for ($i = 0; $i < count($_POST['detalle']['producto']); $i++) {
                $item = new Mapper($this->db, 'movimiento_detalle');
                $item->movimiento_id = $inId[0]['id'];
                $item->producto_id = $_POST['detalle']['producto'][$i];
                $item->cantidad = $_POST['detalle']['cantidad'][$i];
                $item->save();
            }
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (Exception $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function update($id)
    {
        $this->dao->load(array('transfer_id = UuidToBin(?)', $id));
        $this->dao->copyfrom('POST');
        $this->dao->updated_by = $this->current_user_id;
        $this->dao->updated_at = date('Y-m-d H:i:s');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (Exception $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('transfer_id = UuidToBin(?)', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (Exception $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
