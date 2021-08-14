<?php

namespace App\Model;

use DB\SQL\Mapper;

class BackupModel extends BaseModel
{
    private Mapper $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new Mapper($this->db, 'backup');
    }

    public function get()
    {
        return $this->utils->setUp('vw_backup', 'id', 'asc');
    }

    public function nextCorrelative()
    {
        $data = $this->db->exec("select ifnull(max(correlativo), 0) as next from backup");
        return $data[0]['next'] + 1;
    }

    public function insert()
    {
        $this->dao->copyfrom('POST');
        try {
            $this->dao->correlativo = $this->nextCorrelative();
            $this->dao->created_at = date('Y-m-d H:i:s');
            $this->dao->created_by = $this->current_user_id;

            $path = $this->app->get('BACKUPS');
            $filename = $path . uniqid() . '.txt';
            $file = fopen($filename, 'w');
            fwrite($file, 'asdgasfghas');
            fclose($file);

            $this->dao->path = $filename;

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
            unlink($this->dao->path);
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function getOneById($id)
    {
        return $this->db->exec("select * from backup where id = ?", [$id]);
    }
}
    