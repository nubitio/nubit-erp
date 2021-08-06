<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class MenuModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'menu');
    }

    public function all()
    {
        return $this->utils->setUp('vw_menu', 'orden', 'ASC');
    }

    public function lista($filtered, $extended)
    {
        $result = $this->db->exec("CALL sp_menu_lista(?)", $filtered);
        if ($extended) {
            array_unshift($result, array('menu_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function lista_rol($rol_id)
    {
        try {
            if ($rol_id == 0) {
                $result = $this->db->exec("
                    SELECT
                        m.*
                    FROM menu m
                    ORDER BY menu_id, orden
                ");
            } else {
                $result = $this->db->exec("
                    SELECT
                        m.*
                    FROM menu m
                        INNER JOIN menu_rol mr ON mr.menu_id = m.menu_id
                    WHERE mr.rol_id = '$rol_id' AND mr.estado = 1
                    ORDER BY menu_id, orden
                ");
            }
            return ["success" => true, "data" => $result];
        } catch (Exception $e) {
            return ["success" => false, "data" => [], "msg" => $e->getMessage()];
        }
    }

    public function padres()
    {
        $result = $this->db->exec("SELECT * FROM menu WHERE parent_id=0 ORDER BY orden, descripcion");
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
        $this->dao->load(array('menu_id=?', $id));
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
        $this->dao->load(array('menu_id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
