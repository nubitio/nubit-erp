<?php

namespace App\Api;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\Uid\Uuid;

class ApiRepository
{
    /**
     * @param array<string, int> $customerColumns
     * @param array<string, int> $categoryColumns
     * @param array<string, int> $productColumns
     * @param array<string, int> $saleColumns
     * @param array<string, int> $saleDetailColumns
     */
    public function __construct(
        private readonly Connection $db,
        private array $customerColumns = [],
        private array $categoryColumns = [],
        private array $productColumns = [],
        private array $saleColumns = [],
        private array $saleDetailColumns = [],
    ) {
        $this->customerColumns = array_flip([
            'ruc',
            'razon_social',
            'domicilio_fiscal',
            'telefono',
            'email',
            'imagen',
            'price_list_id',
            'estado',
        ]);

        $this->categoryColumns = array_flip([
            'categoria_id',
            'descripcion',
            'parent_id',
            'imagen',
            'estado',
        ]);

        $this->productColumns = array_flip([
            'producto_id',
            'codigo',
            'tipo_id',
            'descripcion',
            'categoria_id',
            'unidad_id',
            'destino_id',
            'stock_minimo',
            'precio',
            'precio_compra',
            'imagen',
            'estado',
        ]);

        $this->saleColumns = array_flip([
            'movimiento_id',
            'tipo_id',
            'fecha',
            'operacion_id',
            'almacen_id',
            'proveedor_id',
            'cliente_id',
            'tipo_pago',
            'tipo_tarjeta',
            'serie',
            'numero',
            'descuento',
            'glosa',
            'orden_id',
            'has_invoice',
            'is_consume',
            'caja_id',
            'estado',
            'date_created',
            'date_updated',
            'created_by',
            'updated_by',
            'igv',
            'recargo_consumo',
            'invoice',
            'subtotal',
            'total',
        ]);

        $this->saleDetailColumns = array_flip([
            'id',
            'movimiento_id',
            'producto_id',
            'cantidad',
            'precio_unitario',
            'descuento',
            'precio_total',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getConfig(): array
    {
        return $this->db->fetchAllAssociative('select * from options');
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getAllLocals(): array
    {
        return $this->db->fetchAllAssociative('select * from local');
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getAllCategories(): array
    {
        return $this->db->fetchAllAssociative('select * from categoria');
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function getOneCategory(string $id): ?array
    {
        $data = $this->db->fetchAllAssociative('select * from categoria where categoria_id = ?', [$id]);
        if ($data !== []) {
            return $data[0];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $category
     *
     * @return array<string, mixed>
     */
    public function createCategory(array $category): array
    {
        $category['categoria_id'] = Uuid::v4();
        $category['estado'] = 1;
        try {
            $filteredCategory = array_intersect_key($category, $this->categoryColumns);
            $this->db->insert('categoria', $filteredCategory);

            return ['success' => true, 'data' => $category, 'msg' => 'Registro insertado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $category
     *
     * @return array<string, mixed>
     */
    public function updateCategory(string $id, array $category): array
    {
        try {
            $filteredCategory = array_intersect_key($category, $this->categoryColumns);
            $this->db->update('categoria', $filteredCategory, ['categoria_id' => $id]);

            return ['success' => true, 'data' => $category, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteCategory(string $id): array
    {
        try {
            $this->db->delete('categoria', ['categoria_id' => $id]);

            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getAllCustomers(): array
    {
        return $this->db->fetchAllAssociative('select * from cliente');
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function getOneCustomer(string $id): ?array
    {
        $data = $this->db->fetchAllAssociative('select * from cliente where ruc = ?', [$id]);
        if ($data !== []) {
            return $data[0];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, mixed>
     */
    public function createCustomer(array $customer): array
    {
        $customer['estado'] = 1;
        try {
            $filteredCustomer = array_intersect_key($customer, $this->customerColumns);
            $this->db->insert('cliente', $filteredCustomer);

            return ['success' => true, 'data' => $customer, 'msg' => 'Registro insertado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $customer
     *
     * @return array<string, mixed>
     */
    public function updateCustomer(string $id, array $customer): array
    {
        try {
            $filteredCustomer = array_intersect_key($customer, $this->customerColumns);
            $this->db->update('cliente', $filteredCustomer, ['ruc' => $id]);

            return ['success' => true, 'data' => $customer, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteCustomer(string $id): array
    {
        try {
            $this->db->delete('cliente', ['ruc' => $id]);

            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getAllProducts(): array
    {
        $sql = '
               select *
               from producto
               where
                   estado = 1
               ';

        return $this->db->fetchAllAssociative($sql);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function getOneProduct(string $id): ?array
    {
        $data = $this->db->fetchAllAssociative('select * from vw_producto where producto_id = ?', [$id]);
        if ($data !== []) {
            return $data[0];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    public function createProduct(array $product): array
    {
        $product['producto_id'] = Uuid::v4();
        $product['tipo_id'] = 2;
        $product['estado'] = 1;
        try {
            $filteredProduct = array_intersect_key($product, $this->productColumns);
            $this->db->insert('producto', $filteredProduct);

            return ['success' => true, 'data' => $product, 'msg' => 'Registro insertado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $product
     *
     * @return array<string, mixed>
     */
    public function updateProduct(string $id, array $product): array
    {
        try {
            $filteredProduct = array_intersect_key($product, $this->productColumns);
            $this->db->update('producto', $filteredProduct, ['producto_id' => $id]);

            return ['success' => true, 'data' => $product, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteProduct(string $id): array
    {
        try {
            $this->db->delete('producto', ['producto_id' => $id]);

            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getAllSales(string $local_id): array
    {
        $result = [];
        $sql = <<<SQL
select
    *
from vw_movimiento
where
    operacion like 'VENTA%'
  and local_id = ?
order by date_created
desc limit 50
SQL;
        $sales = $this->db->fetchAllAssociative($sql, [$local_id]);
        foreach ($sales as $sale) {
            $detail = $this->db->fetchAllAssociative(
                'select * from vw_movimiento_detalle where movimiento_id = ?',
                [$sale['movimiento_id']]
            );
            $sale['detail'] = $detail;
            $result[] = $sale;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function getOneSale(string $id): ?array
    {
        $data = $this->db->fetchAllAssociative('select * from vw_movimiento where movimiento_id = ?', [$id]);
        if ($data !== []) {
            $detail = $this->db->fetchAllAssociative(
                'select * from vw_movimiento_detalle where movimiento_id = ?',
                [$id]
            );
            $data[0]['detail'] = $detail;

            return $data[0];
        }
        return null;
    }

    /**
     * @throws Exception
     */
    public function getOption(string $name): string | int | float | bool | null
    {
        /** @var string|int|float|bool|false|null $data */
        $data = $this->db->fetchOne('select value from options where name = ?', [$name]);

        if (!$data) {
            return null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $sale
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function createSale(array $sale): array
    {
        $uuid = Uuid::v4();
        $operation = $this->db->fetchAllAssociative(
            'select * from operacion where descripcion like ? limit 1',
            ['VENTA%']
        );
        $warehouse = $this->db->fetchAllAssociative(
            'select * from almacen where local_id = ? limit 1',
            [$sale['local_id']]
        );
        $series = $this->db->fetchAllAssociative(
            'select * from local_comprobante where tipo_comprobante_id = ? and local_id = ?',
            ['00', $sale['local_id']]
        );
        $number = $this->db->fetchAllAssociative(
            'select ifnull(max(cast(numero as integer)),0)+1 as number from movimiento where serie = ?',
            [$series[0]['serie']]
        );
        $sale['movimiento_id'] = $uuid;
        $sale['fecha'] = date('Y-m-d');
        $sale['tipo_id'] = 2;
        $sale['operacion_id'] = $operation[0]['operacion_id'];
        $sale['almacen_id'] = $warehouse[0]['almacen_id'];
        $sale['serie'] = $series[0]['serie'];
        $sale['numero'] = $number[0]['number'];
        $sale['has_invoice'] = (1 == $sale['has_invoice']) ? 1 : 0;
        $sale['is_consume'] = (1 == $sale['is_consume']) ? 1 : 0;
        $sale['tipo_pago'] = 1;
        $sale['estado'] = 1;
        $sale['igv'] = (int)$this->getOption('app_option_igv');
        $sale['recargo_consumo'] = (int)$this->getOption('app_option_rc');
        /** @var array<int, array<string, mixed>> $saleDetail */
        $saleDetail = $sale['detail'];
        $sale['subtotal'] = 0;
        foreach ($saleDetail as $item) {
            $sale['subtotal'] += $item['precio_total'];
        }

        $sale['total'] = $sale['subtotal'] - ($sale['subtotal'] * ($sale['descuento'] / 100));
        $filteredSale = array_intersect_key($sale, $this->saleColumns);
        try {
            $this->db->insert('movimiento', $filteredSale);
            foreach ($saleDetail as $item) {
                $item['movimiento_id'] = $sale['movimiento_id'];
                $filteredItem = array_intersect_key($item, $this->saleDetailColumns);
                $this->db->insert('movimiento_detalle', $filteredItem);
            }

            return ['success' => true, 'data' => $sale, 'msg' => 'Registro insertado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $sale
     *
     * @return array<string, mixed>
     */
    public function updateSale(string $id, array $sale): array
    {
        $sale['has_invoice'] = (1 == $sale['has_invoice']) ? 1 : 0;
        $sale['is_consume'] = (1 == $sale['is_consume']) ? 1 : 0;
        $sale['estado'] = 1;
        try {
            $filteredSale = array_intersect_key($sale, $this->saleColumns);
            $this->db->update('movimiento', $filteredSale, ['movimiento_id' => $id]);

            return ['success' => true, 'data' => $sale, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteSale(string $id): array
    {
        try {
            $this->db->delete('movimiento', ['movimiento_id' => $id]);

            return ['success' => true, 'data' => null, 'msg' => 'Registro actualizado'];
        } catch (Exception $exception) {
            return ['success' => false, 'data' => null, 'msg' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function getSalesByDate(string $date): ?array
    {
        $sql = <<<SQL
select
    count(*) as total
from movimiento
where
    year(date_created) = year(:date) and month(date_created) = month(:date)
SQL;

        $data = $this->db->fetchAllAssociative($sql, ['date' => $date]);
        if ($data !== []) {
            return $data[0];
        }
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getWeeklySalesSoles(string $local_id): array
    {
        $sql = '
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
        ';

        return $this->db->fetchAllAssociative($sql, [$local_id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getWeeklySales(): array
    {
        $sql = '
        select
            t.db_date as date,
            t.day_name as day,
            count(m.movimiento_id) as value
        from time_dimension t
        left join movimiento m on (date(m.date_created) = t.db_date) and (m.estado = 1 and m.numero > 0)
        where week(curdate()) = week(t.db_date) and year(curdate()) = year(t.db_date)
        group by t.db_date
        order by t.db_date
        ';

        return $this->db->fetchAllAssociative($sql);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getMonthSalesSoles(string $local_id): array
    {
        $sql = '
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
        ';

        return $this->db->fetchAllAssociative($sql, [$local_id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getMonthSales(): array
    {
        $sql = '
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
        ';

        return $this->db->fetchAllAssociative($sql);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getTopProduct(): array
    {
        $sql = '
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
        ';

        return $this->db->fetchAllAssociative($sql);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getVoucherForMonth(string $local_id): array
    {
        $sql = '
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

        ';

        return $this->db->fetchAllAssociative($sql, [$local_id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getVoucherForMonthSoles(string $local_id): array
    {
        $sql = '
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

        ';

        return $this->db->fetchAllAssociative($sql, [$local_id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getImageCustomer(string $ruc): array
    {
        $sql = "
        SELECT
               cl.razon_social as nombre,
               IFNULL( cl.imagen ,concat('https://ui-avatars.com/api/?name=', cl.razon_social)) as imagen
        FROM cliente cl
        WHERE
            cl.ruc=?
        ";

        return $this->db->fetchAllAssociative($sql, [$ruc]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getImageProduct(string $id): array
    {
        $sql = "
        SELECT
            p.descripcion as nombre,
            IFNULL( p.imagen ,concat('https://ui-avatars.com/api/?name=', p.descripcion)) as imagen
        FROM producto p
        WHERE
            p.producto_id=?
        ";

        return $this->db->fetchAllAssociative($sql, [$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     *
     * @throws Exception
     */
    public function getImageCategory(string $id): array
    {
        $sql = "
        SELECT
            c.descripcion as nombre,
            IFNULL( c.imagen ,concat('https://ui-avatars.com/api/?name=',c.descripcion)) as imagen
        FROM categoria c
        WHERE
            c.categoria_id=?
        ";

        return $this->db->fetchAllAssociative($sql, [$id]);
    }

    /**
     * @return array<string, string|int|bool>|false
     *
     * @throws Exception
     */
    public function getUserByName(string $username): array | false
    {
        /** @var array<string, string|int|bool>|false $data */
        $data = $this->db->fetchAssociative('SELECT * FROM usuario WHERE username = ?', [$username]);

        return $data;
    }
}
