<?php

namespace App\Model;

class ProductoModel extends BaseModel
{
    private $dao;

    public function __construct()
    {
        parent::__construct();
        $this->dao = new \DB\SQL\Mapper($this->db, 'producto');
    }

    public function all($tipo)
    {
        if ($tipo == 2) {
            $format = "tipo_id = %s and local_id = '%s'";
            $filter = sprintf($format, $tipo, $this->current_local_id);
        } else {
            $format = "tipo_id = %s";
            $filter = sprintf($format, $tipo);
        }
        return $this->utils->setUp('vw_producto', 'descripcion', 'ASC', $filter);
    }

    public function get_all($tipo)
    {
        return $this->utils->setUp('vw_producto_adm', 'descripcion', 'ASC', "tipo_id={$tipo}");
    }

    public function lista($filtered, $extended)
    {
        if ($this->app->get('MODE') == 'POS') {
            return $this->listafull();
        }
        if ($filtered) {
            $result = $this->db->exec("
                SELECT * FROM producto p
                INNER JOIN producto_local pl on pl.producto_id=p.producto_id 
                WHERE 
                      estado=1 AND 
                      p.tipo_id=2 AND 
                      p.estado=1 AND 
                      pl.activo=1 AND 
                      pl.local_id=?
                ", $this->current_local_id);
        } else {
            $result = $this->db->exec("SELECT * FROM producto ORDER BY descripcion");
        }
        if ($extended) {
            array_unshift($result, array('producto_id' => '', 'descripcion' => 'TODOS', 'estado' => '1'));
        }
        return $result;
    }

    public function listafull()
    {
        if ($this->current_local_id == '' || $this->current_local_id == null) {
            $this->current_local_id = '1f8d9920-bf81-11e7-91af-5600005a2745';
        }
        return $this->db->exec("
          select
            p.producto_id,
            p.codigo,
            p.descripcion,
            p.categoria_id,
            p.destino_id,
            pl.precio,
            CASE WHEN p.destino_id = 1 THEN 'COCINA' WHEN p.destino_id = 2 THEN 'BARRA' ELSE 'COCINA/BARRA' END AS destino
          from producto p
            inner join producto_local pl on pl.producto_id=p.producto_id
          where
            p.tipo_id=2
            and p.estado=1
            and pl.activo=1
            and pl.local_id=?
          order by
           p.descripcion", $this->current_local_id);
    }

    public function add($tipo)
    {
        $key = $this->db->exec("SELECT UUID() AS uuid");
        $uuid = $key[0]['uuid'];
        $this->dao->copyFrom('POST');
        $this->dao->producto_id = $uuid;
        $this->dao->tipo_id = $tipo;
        try {
            $this->dao->save();
            if ($this->dao->tipo_id == 2) {
                $ext = new \DB\SQL\Mapper($this->db, 'producto_local');
                $ext->local_id = $this->current_local_id;
                $ext->producto_id = $this->dao->producto_id;
                $ext->precio = $this->dao->precio;
                $ext->activo = 1;
                $ext->save();
            }
            return array('success' => true, 'msg' => 'Registro guardado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function edit($id)
    {
        $this->dao->load(array('producto_id=?', $id));
        $this->dao->copyFrom('POST');
        try {
            $this->dao->update();
            if ($this->dao->tipo_id == 2) {
                $ext = new \DB\SQL\Mapper($this->db, 'producto_local');
                $ext->load(array('local_id=? and producto_id=?', $this->current_local_id, $this->dao->producto_id));
                if (!$ext->dry()) {
                    $ext->precio = $this->dao->precio;
                    $ext->update();
                } else {
                    $ext->local_id = $this->current_local_id;
                    $ext->producto_id = $this->dao->producto_id;
                    $ext->precio = $this->dao->precio;
                    $ext->activo = 1;
                    $ext->save();
                }
            }
            return array('success' => true, 'msg' => 'Registro actualizado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function delete($id)
    {
        $this->dao->load(array('producto_id=?', $id));
        try {
            if ($this->dao->tipo_id == 2) {
                $this->db->exec("delete from producto_local where producto_id=?", $id);
            }
            $this->dao->erase();
            return array('success' => true, 'msg' => 'Registro eliminado.');
        } catch (\PDOException $e) {
            return array('msg' => $e->getMessage());
        }
    }

    public function exportar_productos()
    {
        return $this->db->exec("call sp_reporte_productos(?)", [$this->current_local_id]);
    }
}
