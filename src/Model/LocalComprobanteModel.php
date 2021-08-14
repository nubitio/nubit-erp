<?php

namespace App\Model;

class LocalComprobanteModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'local_comprobante');
    }

    public function all()
    {
        return $this->utils->setUp('vw_local_comprobante', 'local', 'ASC');
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

    public function edit($local, $tipo, $serie)
    {
        $this->dao->load(array('local_id=? and tipo_comprobante_id=? and serie=?', $local, $tipo, $serie));
        $this->dao->copyFrom('POST');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($local, $tipo, $serie)
    {
        $this->dao->load(array('local_id=? and tipo_comprobante_id=? and serie=?', $local, $tipo, $serie));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
