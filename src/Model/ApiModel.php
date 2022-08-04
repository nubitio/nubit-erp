<?php

namespace App\Model;

use App\Domain\Invoice\InvoiceCalculator;
use Symfony\Component\Uid\Uuid;

class ApiModel extends BaseModel
{
    private OptionsModel $optionsDao;

    public function __construct()
    {
        parent::__construct();
        $this->optionsDao = new OptionsModel();
    }

    public function getConfig()
    {
        return $this->db->exec("select * from options");
    }

    public function getAllLocals()
    {
        return $this->db->exec("select * from local");
    }

    public function getAllCategories()
    {
        return $this->db->exec('select * from categoria');
    }

    public function getOneCategory($id)
    {
        $data = $this->db->exec('select * from categoria where categoria_id = ?', [$id]);
        if ($data) {
            return $data[0];
        } else {
            return null;
        }
    }

    public function createCategory($category): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'categoria');
        $repository->copyfrom($category);
        $repository->categoria_id = Uuid::v4();
        $repository->estado = 1;
        try {
            $repository->save();
            $category['categoria_id'] = $repository->categoria_id;
            return ['success' => true, 'data' => $category, 'msg' => 'Registro insertado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function updateCategory($id, $category): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'categoria');
        $repository->load(['categoria_id = ?', $id]);
        $repository->copyfrom($category);
        try {
            $repository->update();
            return ['success' => true, 'data' => $category, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function deleteCategory($id): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'categoria');
        $repository->load(['categoria_id = ?', $id]);
        try {
            $repository->erase();
            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function getAllCustomers()
    {
        return $this->db->exec('select * from cliente');
    }

    public function getOneCustomer($id)
    {
        $data = $this->db->exec('select * from cliente where ruc = ?', [$id]);
        if ($data) {
            return $data[0];
        } else {
            return null;
        }
    }

    public function createCustomer($customer): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'cliente');
        $repository->copyfrom($customer);
        $repository->estado = 1;
        try {
            $repository->save();
            return ['success' => true, 'data' => $customer, 'msg' => 'Registro insertado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function updateCustomer($id, $customer): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'cliente');
        $repository->load(['ruc = ?', $id]);
        $repository->copyfrom($customer);
        try {
            $repository->update();
            return ['success' => true, 'data' => $customer, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function deleteCustomer($id): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'cliente');
        $repository->load(['ruc = ?', $id]);
        try {
            $repository->erase();
            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function getAllProducts($local_id): array
    {
        $sql= "
               select * 
               from vw_producto vw
               where 
                   vw.local_id=?
               ";
        return $this->db->exec($sql,[$local_id]);
    }
    public function getOneProduct($id)
    {
        $data = $this->db->exec('select * from vw_producto where producto_id = ?', [$id]);
        if ($data) {
            return $data[0];
        } else {
            return null;
        }
    }

    public function createProduct($local_id,$product): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'producto');
        $repository->copyfrom($product);
        $repository->producto_id = Uuid::v4();
        $repository->tipo_id = 2;
        $repository->estado = 1;
        try {
            $repository->save();
            $product['producto_id']=$repository->producto_id;
            $ext = new \DB\SQL\Mapper($this->db, 'producto_local');
            $ext->local_id = $local_id;
            $ext->producto_id = $repository->producto_id;
            $ext->precio = $repository->precio;
            $ext->activo = 1;
            $ext->save();
            return ['success' => true, 'data' => $product, 'msg' => 'Registro insertado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function updateProduct($local_id,$id, $product): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'producto');
        $repository->load(['producto_id = ?', $id]);
        $repository->copyfrom($product);
        try {
            $repository->update();
            $ext = new \DB\SQL\Mapper($this->db, 'producto_local');
            $ext->load(array('local_id=? and producto_id=?', $local_id, $repository->producto_id));

            if (!$ext->dry()) {
                $ext->precio = $repository->precio;
                $ext->update();
            } else {
                $ext->local_id = $local_id;
                $ext->producto_id = $repository->producto_id;
                $ext->precio = $repository->precio;
                $ext->activo = 1;
                $ext->save();
            }
            return ['success' => true, 'data' => $product, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }


    public function deleteProduct($id): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'producto');
        $repository->load(['producto_id = ?', $id]);
        try {
            $repository->erase();
            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function getAllSales($local_id): array
    {
        $result = [];
        $sales = $this->db->exec("select * from vw_movimiento where operacion like 'VENTA%' and local_id = ? order by date_created desc limit 50", [$local_id]);
        foreach ($sales as $sale) {
            $detail = $this->db->exec("select * from vw_movimiento_detalle where movimiento_id = ?", [$sale['movimiento_id']]);
            $sale['detail'] = $detail;
            array_push($result, $sale);
        }
        return $result;
    }

    public function getOneSale($id)
    {
        $data = $this->db->exec('select * from vw_movimiento where movimiento_id = ?', [$id]);
        if ($data) {
            $detail = $this->db->exec("select * from vw_movimiento_detalle where movimiento_id = ?", $id);
            $data[0]['detail'] = $detail;
            return $data[0];
        } else {
            return null;
        }
    }

    public function getOption($name)
    {
        try {
            return $this->optionsDao->getByName($name);
        } catch (\Exception $e) {
            $this->app->error(500, "No se pudo obtener la opcion {$name}");
        }
    }

    public function createSale($sale): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'movimiento');
        $repository->copyfrom($sale);
        $uuid = $this->db->exec("select uuid() as uuid");
        $operation = $this->db->exec("select * from operacion where descripcion like ? limit 1", ['VENTA%']);
        $warehouse = $this->db->exec("select * from almacen where local_id = ? limit 1", [$sale['local_id']]);
        $series = $this->db->exec("select * from local_comprobante where tipo_comprobante_id = ? and local_id = ?", ['00', $sale['local_id']]);
        $number = $this->db->exec("select ifnull(max(cast(numero as integer)),0)+1 as number from movimiento where serie = ?", [$series[0]['serie']]);
        $repository->movimiento_id = $uuid[0]['uuid'];
        $repository->fecha = date('Y-m-d');
        $repository->tipo_id = 2;
        $repository->operacion_id = $operation[0]['operacion_id'];
        $repository->almacen_id = $warehouse[0]['almacen_id'];
        $repository->serie = $series[0]['serie'];
        $repository->numero = $number[0]['number'];
        $repository->has_invoice = ($sale['has_invoice'] == 1) ? 1 : 0;
        $repository->is_consume = ($sale['is_consume'] == 1) ? 1 : 0;
        $repository->tipo_pago = 1;
        $repository->estado = 1;
        $repository->igv = (int)$this->getOption('app_option_igv');
        $repository->recargo_consumo = (int)$this->getOption('app_option_rc');
        try {
            $repository->save();
            foreach ($sale['detail'] as $item) {
                $detail = new \DB\SQL\Mapper($this->db, 'movimiento_detalle');
                $detail->copyfrom($item);
                $detail->movimiento_id = $repository->movimiento_id;
                $detail->save();
            }
            $sale['movimiento_id'] = $repository->movimiento_id;
            return ['success' => true, 'data' => $sale, 'msg' => 'Registro insertado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function updateSale($id, $sale): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'movimiento');
        $repository->load(['movimiento_id = ?', $id]);
        $repository->copyfrom($sale);
        $repository->has_invoice = ($sale['has_invoice'] == 1) ? 1 : 0;
        $repository->is_consume = ($sale['is_consume'] == 1) ? 1 : 0;
        $repository->estado = 1;
        try {
            $repository->update();
            return ['success' => true, 'data' => $sale, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function deleteSale($id): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'movimiento');
        $repository->load(['movimiento_id = ?', $id]);
        try {
            $repository->erase();
            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function makeInvoice($id, $local_id): array
    {
        $sale = $this->db->exec("SELECT * FROM vw_movimiento WHERE movimiento_id = ?", array($id));
        $invoiceCalculator = new InvoiceCalculator($sale[0]['total'], $sale[0]['descuento'] ?? 0.0, $sale[0]['igv'], $sale[0]['recargo_consumo'] ?? 0.0);
        $invoiceAmounts = $invoiceCalculator->calculate();
        try {
            $this->db->exec('update movimiento set has_invoice = 1 where movimiento_id = ?', [$id]);
            $result = $this->db->exec('CALL sp_make_invoice(?,?,?,?,?,?,?)', [$id, $local_id, $invoiceAmounts->getSubtotal(), $invoiceAmounts->getTaxedAmount(), $invoiceAmounts->getDiscountAmount(), $invoiceAmounts->getTax(), $invoiceAmounts->getTotal()]);
            return ["success" => true, "data" => $result, "msg" => 'Comprobante registrado'];
        } catch (\Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }

    public function getSalesByDate(string $date)
    {
        $data = $this->db->exec("select count(*) as total from movimiento where year(date_created) = year(:date) and month(date_created) = month(:date)", ['date' => $date]);
        if ($data) {
            return $data[0];
        } else {
            return null;
        }
    }

    public function getSalesByDayForWeek(string $date)
    {
        $data = $this->db->exec("select count(*) as total from movimiento where year(date_created) = year(:date) and month(date_created) = month(:date)", ['date' => $date]);
        if ($data) {
            return $data[0];
        } else {
            return null;
        }
    }

    public function getWeeklySalesSoles($local_id): array//venta de domingo a sabado de una semana sole
    {
        $sql = "
        select
	        td.DAY AS dia,
	        sum( IFNULL( v.p_venta, 0 ) ) AS ventatotal 
        from time_dimension td
        left join vw_descuento v ON td.db_date = v.fecha 
         and v.estado = 1 
         and v.local_id = ?
        where
            month(curdate()) = month(td.db_date) and year(curdate()) = year(td.db_date)
        group by
            td.DAY 
        order by
            td.DAY
        ";
        return $this->db->exec($sql, [$local_id]);
    }

    public function getWeeklySales()//venta de domingo a sabado de una semana cantidad
    {
        $sql = "
        select
            t.db_date as date,
            t.day_name as day,
            count(m.movimiento_id) as value
        from time_dimension t
        left join movimiento m on (date(m.date_created) = t.db_date) and (m.estado = 1 and m.numero > 0)
        where week(curdate()) = week(t.db_date) and year(curdate()) = year(t.db_date)
        group by t.db_date
        order by t.db_date
        ";
        return $this->db->exec($sql);
    }

    public function getMonthSalesSoles($local_id): array//venta de domingo a sabado de una semana sole
    {
        $sql = "
        select
	        td.DAY AS dia,
	        sum( IFNULL( v.p_venta, 0 ) ) AS ventatotal 
        from time_dimension td
        left join vw_descuento v ON td.db_date = v.fecha 
         and v.estado = 1 
         and local_id = ?
        where
            month(curdate()) = month(td.db_date) and year(curdate()) = year(td.db_date)
        group by
            td.DAY 
        order by
            td.DAY
        ";
        return $this->db->exec($sql, [$local_id]);
    }

    public function getMonthSales()//venta por dia en un respectivo mes en cantidad
    {
        $sql = "
        select
            t.db_date as date,
            t.day	as fecha,
            t.month_name as month,
            count(m.movimiento_id) as value
        from time_dimension t
        left join movimiento m on (date(m.date_created) = t.db_date) and (m.estado = 1 and m.numero > 0)
        where month(curdate()) = month(t.db_date) and year(curdate()) = year(t.db_date)
        group by t.db_date
        order by t.db_date
        ";
        return $this->db->exec($sql);
    }

    public function getTopProduct()//top 10 productos mas vendidos
    {
        $sql = "
        select
			p.producto_id,
            p.descripcion as product,
            sum(md.cantidad) as total
            
        from time_dimension t 
        left join movimiento m on (date(m.date_created) = t.db_date) and (m.estado = 1 and m.numero > 0)
        inner join movimiento_detalle md on m.movimiento_id = md.movimiento_id
        inner join producto p on md.producto_id = p.producto_id
        where month(curdate()) = month(t.db_date) and year(curdate()) = year(t.db_date)
        group by p.descripcion, p.producto_id
        order by total 
        limit 10
        ";
        return $this->db->exec($sql);
    }

    public function getTopProductM()//top 10 productos menos vendidos
    {
        $sql = "
        select
			p.producto_id,
            p.descripcion as product,
            sum(md.cantidad) as total
            
        from time_dimension t 
        left join movimiento m on (date(m.date_created) = t.db_date) and (m.estado = 1 and m.numero > 0)
        inner join movimiento_detalle md on m.movimiento_id = md.movimiento_id
        inner join producto p on md.producto_id = p.producto_id
        where month(curdate()) = month(t.db_date) and year(curdate()) = year(t.db_date)
        group by p.descripcion, p.producto_id
        order by total desc
        limit 10
        ";
        return $this->db->exec($sql);
    }

    public function getVoucherForMonth($local_id): array//comprobantes totales por mes
    {
        $sql = "
        SELECT
        
               tc.codigo,
               tc.descripcion,
               lc.serie,
               count( c.comprobante_id ) AS totalComprobante 
        FROM local_comprobante lc
        INNER JOIN tipo_comprobante tc ON tc.codigo = lc.tipo_comprobante_id
        LEFT JOIN comprobante c ON lc.serie = c.serie 
        AND ( MONTH ( c.fecha ) = MONTH ( curdate() ) AND YEAR ( c.fecha ) = YEAR ( CURDATE()) ) 
        WHERE local_id=?
        GROUP BY tc.codigo 
        
        ";
        return $this->db->exec($sql, [$local_id]);
    }

    public function getVoucherForMonthSoles($local_id): array//comprobantes totales por mes (soles)
    {
        $sql = "
        SELECT
        
               tc.codigo,
               tc.descripcion,
               lc.serie,
               IFNULL(sum( c.total ),0) AS totalComprobanteSoles 
        FROM local_comprobante lc
        INNER JOIN tipo_comprobante tc ON tc.codigo = lc.tipo_comprobante_id
        LEFT JOIN comprobante c ON lc.serie = c.serie 
        AND ( MONTH ( c.fecha ) = MONTH ( curdate() ) AND YEAR ( c.fecha ) = YEAR ( CURDATE()) ) 
        WHERE local_id=?
        GROUP BY tc.codigo 
        
        ";
        return $this->db->exec($sql, [$local_id]);
    }

    public function getImageCustomer($ruc)
    {
        $sql = "
        SELECT
               cl.ruc as ruc,
               cl.razon_social as nombre,
               IFNULL( cl.imagen ,concat('https://i.pravatar.cc/',cl.ruc)) as imagen
        FROM cliente cl   
        WHERE 
            cl.ruc=?
        ";

        return $this->db->exec($sql,[$ruc]);
    }

    public function getImageProduct($id)
    {
        $sql = "
        SELECT
            p.descripcion as nombre,
            p.imagen as imagen       
        FROM vw_producto p
        WHERE
            p.producto_id=?
        ";

        return $this->db->exec($sql,[$id]);
    }
    public function getImageCategory($id)
    {
        $sql = "
        SELECT
            c.descripcion as nombre,
            IFNULL( c.imagen ,concat('https://ui-avatars.com/api/?name=',c.descripcion)) as imagen    
        FROM categoria c
        WHERE
            c.categoria_id=?
        ";

        return $this->db->exec($sql,[$id]);
    }


}