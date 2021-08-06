<?php

namespace App\Models;

class OptionsModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new DB\SQL\Mapper($this->db, 'options');
    }

    public function get()
    {
        return $this->utils->setUp('options', 'id', 'asc');
    }

    public function getAll()
    {
        return $this->db->exec("select * from options");
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getByName($name)
    {
        $row = $this->db->exec("select IFNULL(value, '') as value from options where name = ?", $name);
        return $row[0]['value'];
    }

    public function get_list($filtered)
    {
        if ($filtered) {
            $result = $this->db->exec('select * from options');
        } else {
            $result = $this->db->exec('select * from options');
        }
        return $result;
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
        $this->dao->load(array('id=?', $id));
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
        $this->dao->load(array('id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
