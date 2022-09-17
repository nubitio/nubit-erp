<?php

namespace App\Model;

class RolModel extends BaseModel
{

    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'rol');
    }

    public function all()
    {
        return $this->utils->setUp('rol', 'descripcion');
    }

    public function lista($filtered, $extended)
    {
        if ($filtered) {
            $result = $this->db->exec("SELECT * FROM rol WHERE 1=1 ORDER BY descripcion");
        } else {
            $result = $this->db->exec("SELECT * FROM rol ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('rol_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
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
        $this->dao->load(array('rol_id=?', $id));
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
        $this->dao->load(array('rol_id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function get_mr($id)
    {
        try {
            $data = $this->db->exec('CALL sp_menu_by_rol(?)', $id);
            return array('success' => true, 'data' => $data);
        } catch (Exception $e) {
            return array('success' => false, 'data' => [], 'msg' => $e->getMessage());
        }
    }

    public function set_mr($id, $data)
    {
        try {
            $this->db->begin();
            $this->db->exec("delete from menu_rol where rol_id=?", $id);
            foreach ($data as $item) {
                $this->db->exec("insert into menu_rol (menu_id, rol_id, estado) values({$item}, {$id}, 1)");
            }
            $this->db->commit();
            return array('success' => true, 'data' => [], 'msg' => 'Registro guardado.');
        } catch (Exception $e) {
            return array('success' => false, 'data' => [], 'msg' => $e->getMessage());
        }
    }
}
