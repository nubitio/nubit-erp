<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class RolModel extends DB\SQL\Mapper
{

    private $_db;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'rol');
        $this->_db = $db;
    }

    public function all()
    {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $rows = isset($_POST['rows']) ? intval($_POST['rows']) : 10;
        $sort = isset($_POST['sort']) ? strval($_POST['sort']) : 'rol_id';
        $order = isset($_POST['order']) ? strval($_POST['order']) : 'asc';
        $offset = ($page - 1) * $rows;
        $result = array();
        $rs = $this->_db->query("SELECT count(*) FROM rol");
        $row = $rs->fetch(PDO::FETCH_NUM);
        $result["total"] = $row[0];
        $rs = $this->_db->query("SELECT * FROM rol ORDER BY $sort $order LIMIT $offset,$rows");
        $items = array();
        while ($row = $rs->fetch(PDO::FETCH_OBJ)) {
            array_push($items, $row);
        }
        $result["rows"] = $items;
        return $result;
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
        $this->copyFrom('POST');
        try {
            $this->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->load(array('rol_id=?', $id));
        $this->copyFrom('POST');
        try {
            $this->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->load(array('rol_id=?', $id));
        try {
            $this->erase();
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
