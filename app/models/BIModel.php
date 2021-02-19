<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class BIModel extends BaseModel
{

    public function __construct()
    {
        parent::__construct();
    }

    public function venta_dia()
    {
        $result = $this->db->exec("CALL sp_bi_venta_por_dia (?)", array($this->current_local_id));
        return $result;
    }

    public function venta_semana()
    {
        return $this->db->exec("call sp_bi_venta_por_semana (?)", array($this->current_local_id));
    }

    public function venta_mes()
    {
        return $this->db->exec("call sp_bi_venta_por_mes (?)", array($this->current_local_id));
    }

    public function productRotation($lapse = 'week')
    {
        return $this->db->exec("CALL sp_bi_product_rotation (?, ?)", array($this->current_local_id, $lapse));
    }
}
