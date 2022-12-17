<?php

namespace App\Model;

class UsuarioModel extends BaseModel
{

    protected $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'usuario');
    }

    /**
     * Obtiene todos los registros
     * @return array
     */
    public function all()
    {
        return $this->utils->setUp('vw_usuario', 'username');
    }

    public function getById($id)
    {
        $data = $this->db->exec('select * from usuario where usuario_id = ?', $id);
        if ($data)
            return $data[0];
        return null;
    }

    public function getByName($username)
    {
        $data = $this->db->exec('select * from usuario where username = ?', $username);
        if ($data)
            return $data[0];
        return null;
    }

    public function getByPin($pin)
    {
        $this->dao->load(array('pin=?', $pin));
        return $this->dao->query;
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
        $result->setFetchMode(\PDO::FETCH_ASSOC);
        return $result->fetch();
    }

    public function changePassword($id): array
    {
        try {
            $this->db->exec(
                'UPDATE usuario SET password = ? WHERE usuario_id = ?',
                array(
                    password_hash($_POST['password'], PASSWORD_DEFAULT),
                    $id
                )
            );
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function add()
    {
        $this->dao->copyfrom('POST');
        $this->dao->password = password_hash($this->dao->password, PASSWORD_DEFAULT);
        if ($this->dao->pin == '') {
            $this->dao->pin = null;
        }
        try {
            $this->dao->save();
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => 'No se pudo agregar el usuario porque ya existe en la base de datos, use otro nombre', $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->dao->load(array('usuario_id=?', $id));
        $password = $this->dao->password;
        $this->dao->copyFrom('POST');
        if (empty($this->dao->password)) {
            $this->dao->password = $password;
        } else {
            $this->dao->password = password_hash($this->dao->password, PASSWORD_DEFAULT);
        }
        if ($this->dao->pin == '') {
            $this->dao->pin = null;
        }
        try {
            $this->dao->update();
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => 'No se puede actualizar el usuario, ese nombre ya esta utilizado', $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('usuario_id=?', $id));
        try {
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }
}
