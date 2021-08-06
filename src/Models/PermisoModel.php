<?php

namespace App\Models;

class PermisoModel extends DB\SQL\Mapper
{

    private $_db;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'permiso');
        $this->_db = $db;
    }

    public function all()
    {
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $rows = isset($_POST['rows']) ? intval($_POST['rows']) : 10;
        $sort = isset($_POST['sort']) ? strval($_POST['sort']) : 'permiso_id';
        $order = isset($_POST['order']) ? strval($_POST['order']) : 'asc';
        $offset = ($page - 1) * $rows;
        $result = array();
        $rs = $this->_db->query("SELECT count(*) FROM permiso");
        $row = $rs->fetch(PDO::FETCH_NUM);
        $result["total"] = $row[0];
        $rs = $this->_db->query("SELECT * FROM permiso ORDER BY $sort $order LIMIT $offset,$rows");
        $items = array();
        while ($row = $rs->fetch(PDO::FETCH_OBJ)) {
            array_push($items, $row);
        }
        $result["rows"] = $items;
        return $result;
    }

    public function lista()
    {
        $result = $this->_db->exec("SELECT * FROM permiso ORDER BY descripcion");
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
        $this->load(array('permiso_id=?', $id));
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
        $this->load(array('permiso_id=?', $id));
        try {
            $this->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}

?>