<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class UsuarioModel extends DB\SQL\Mapper
{

    protected $db;
    protected $utils;

    public function __construct(DB\SQL $db)
    {
        parent::__construct($db, 'usuario');
        $this->db = $db;
        $this->utils = new ModelUtils($db);
    }

    /**
     * Obtiene todos los registros
     * @return array
     */
    public function all()
    {
        return $this->utils->setUp('vw_usuario', 'username');
    }

    public function init()
    {
        $this->load(array('username=?', 'root'));
        if (!$this->usuario_id > 0) {
            $this->db->exec(
                'INSERT INTO usuario VALUES(?,?,?,?,?,?,?)',
                array(
                    1 => null,
                    2 => 'root',
                    3 => password_hash('root', PASSWORD_DEFAULT),
                    4 => 0,
                    5 => 1,
                    6 => date('Y-m-d H:i:s'),
                    7 => null
                )
            );
        }
    }

    public function getById($id)
    {
        $this->load(array('usuario_id=?', $id));
        return $this->query;
    }

    public function getByName($name)
    {
        $this->load(array('username=?', $name));
        return $this->query;
    }

    public function getByPin($pin)
    {
        $this->load(array('pin=?', $pin));
        return $this->query;
    }

    public function getOne($name)
    {
        $result = $this->db->query("
      SELECT
        usuario_id,
        username,
        rol_id
      FROM
        usuario
      WHERE username='$name'
    ");
        $result->setFetchMode(PDO::FETCH_ASSOC);
        return $result->fetch();
    }

    public function spassword($id)
    {
        try {
            $this->db->exec(
                'UPDATE usuario SET password=? WHERE usuario_id=?',
                array(
                    1 => password_hash($_POST['password'], PASSWORD_DEFAULT),
                    2 => $id
                )
            );
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function add()
    {
        $this->copyfrom('POST');
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        if ($this->pin == '') {
            $this->pin = null;
        }
        try {
            $this->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->load(array('usuario_id=?', $id));
        $password = $this->password;
        $this->copyFrom('POST');
        if (empty($this->password)) {
            $this->password = $password;
        } else {
            $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        }
        if ($this->pin == '') {
            $this->pin = null;
        }
        try {
            $this->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->load(array('usuario_id=?', $id));
        try {
            $this->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}

?>