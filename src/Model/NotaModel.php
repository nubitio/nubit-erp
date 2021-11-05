<?php

namespace App\Model;

use DB\SQL\Mapper;

class NotaModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'nota');
    }

    public function get()
    {
        return $this->utils->setUp('vw_nota', 'created_at, correlativo', 'desc, desc');
    }

    /**
     * @param $id
     * @return array|false
     */
    public function getOne($id)
    {
        $data = $this->db->exec("
            select
                n.nota_id,
                e.ruc as company_id,
                n.tipo_id as doc_type,
                n.serie as doc_series,
                n.correlativo as doc_number,
                n.fecha as doc_date,
                c.email as email_cliente,
                sum(nd.precio_total) as doc_sale_amount,
                0 as doc_discount_amount
            from nota n
            inner join nota_detalle nd on n.nota_id = nd.nota_id
            inner join movimiento m on n.movimiento_id = m.movimiento_id
            inner join almacen a ON a.almacen_id = m.almacen_id
            inner join `local` l ON l.local_id = a.local_id
		    inner join empresa e ON e.empresa_id = l.empresa_id
            inner join cliente c on m.cliente_id = c.ruc
            where n.nota_id = ?
        ", [$id]);
        if ($data[0]['nota_id']) {
            return ['header' => $data[0]];
        } else {
            return false;
        }
    }

    public function insert()
    {
        $this->dao->copyfrom('POST');
        $this->dao->created_by = $this->current_user_id;
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function update($id)
    {
        $this->dao->load(array('id=?', $id));
        $this->dao->copyfrom('POST');
        $this->dao->updated_by = $this->current_user_id;
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
