<?php

namespace App\Model;

use DB\SQL\Mapper;

class CategoriaModel extends BaseModel
{
    private Mapper $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'categoria');
    }

    public function get()
    {
        return $this->utils->setUp('categoria', 'descripcion', 'asc');
    }

    public function getList($type)
    {
        if ($type == 0) {
            return $this->db->exec("select * from categoria where ifnull(parent_id, '') = ''");
        }
        return $this->db->exec("
            SELECT
                p.categoria_id,
                case when ifnull(c.parent_id, '') != '' then CONCAT(p.descripcion,'-',c.descripcion) ELSE p.descripcion end as descripcion,
                p.parent_id,
                p.imagen,
                p.estado
            FROM
                categoria p
                LEFT JOIN categoria c ON c.parent_id = p.categoria_id
        ");
    }

    public function insert()
    {
        $this->dao->copyfrom('POST');
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function update($id)
    {
        $this->dao->load(array('categoria_id=?', $id));
        $this->dao->copyfrom('POST');
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('categoria_id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
    