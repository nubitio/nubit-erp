<?php

namespace App\Models;

class ApiModel extends BaseModel
{
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

    public function getAllProducts()
    {
        return $this->db->exec('select * from vw_producto');
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

    public function createProduct($product): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'producto');
        $repository->copyfrom($product);
        $repository->tipo_id = 2;
        $repository->estado = 1;
        try {
            $repository->save();
            $product['producto_id'] = $repository->producto_id;
            return ['success' => true, 'data' => $product, 'msg' => 'Registro insertado'];
        } catch (Exception $e) {
            return ['success' => false, 'data' => null, 'msg' => $e->getMessage()];
        }
    }

    public function updateProduct($id, $product): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'producto');
        $repository->load(['producto_id = ?', $id]);
        $repository->copyfrom($product);
        try {
            $repository->update();
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

    public function createSale($sale): array
    {
        $repository = new \DB\SQL\Mapper($this->db, 'movimiento');
        $repository->copyfrom($sale);
        $uuid = $this->db->exec("select uuid() as uuid");
        $operation = $this->db->exec("select * from operacion where descripcion = ?", ['VENTA TICKET']);
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
        try {
            $warehouse = $this->db->exec("select * from almacen where local_id = ? limit 1", [$local_id]);
            $result = $this->db->exec('CALL sp_make_invoice(?,?)', [$id, $local_id]);
            return ["success" => true, "data" => $result, "msg" => 'Comprobante registrado'];
        } catch (Exception $e) {
            return ["success" => false, "data" => null, "msg" => $e->getMessage()];
        }
    }
}